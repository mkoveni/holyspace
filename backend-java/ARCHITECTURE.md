# Architecture decision: clean architecture and DDD

The Java backend uses clean architecture and Domain-Driven Design (DDD). Build a
modular monolith organised by bounded context. Each context contains `domain`,
`application`, `infrastructure`, and `presentation` packages. Dependencies point
inward toward the domain.

## Domain-Driven Design

Identity Access is the first bounded context. It owns account identity, account
lifecycle, roles, permissions and authentication policies. People owns church
membership and personal records; a login account is not a Person aggregate.
Cross-context integration uses published application contracts instead of sharing
aggregates or accessing another context's persistence.

`Account` is an aggregate root identified by its account ID. Registration,
reconstitution and state changes enforce its invariants. Profile updates,
password-hash changes, enabling and disabling return a new aggregate state while
preserving identity, username and creation time. Equality is based on identity.
`Username`, `EmailAddress`, `DisplayName`, `PasswordHash` and `AccountStatus` are
immutable value objects. Sensitive hashes are redacted from diagnostic strings.

`AccountManagementPolicy` owns the rule that at least one active account manager
must remain. The application supplies the count within a transaction; the domain
policy has no database dependency. Repository contracts for aggregate persistence
belong to the domain. Read-only query contracts belong to the application and do
not expose aggregate writes.

Use domain entities, value objects and policies where they express business
invariants. Add domain events when an actual business interaction requires them;
do not introduce empty aggregates or a separate service for every table.

## Clean architecture layers

- **Domain:** aggregate roots, value objects, policies, repository interfaces and
  domain failures. No Spring, HTTP, jOOQ or application dependencies.
- **Application:** one handler per use case, with a typed immutable Command/Query
  record and one public `handle` entry point. Each command and its handler live
  together in a dedicated use-case package, with separate source files, such as
  `application.command.createaccount.CreateAccountCommand` and
  `application.command.createaccount.CreateAccountHandler`. Commands are top-level
  records rather than nested handler types. Queries follow the same structure:
  `application.query.getaccount.GetAccountQuery` and `GetAccountHandler` live in
  separate files within their own use-case package. Handlers coordinate domain behaviour,
  transactions and external capabilities declared in `application.contract`.
  Queries depend on read-only contracts and never perform maintenance or writes.
- **Infrastructure:** implementations of domain repositories and application
  contracts using generated, type-safe jOOQ SQL, Spring Security, password hashing, secure tokens and
  transaction management. Dependency wiring lives here.
- **Presentation:** HTTP validation and translation to application commands and
  queries. Interfaces under `identityaccess.presentation.api` define HTTP routes,
  transport records, input validation and OpenAPI descriptions. Controllers
  implement these interfaces and call handlers, never repositories or database
  code. Runtime permission enforcement remains on the controllers.

Spring annotations stay out of domain and application code. `IdentityConfiguration`
composes handlers. `UnitOfWork` supplies command transactions through a Spring
implementation. Each request loads current roles and permissions; permission
claims are not frozen into an access token.

Account and access writes use PostgreSQL transactions. A transaction-scoped
advisory lock serialises bootstrap and changes that could remove the last account
manager. Account row locks serialise aggregate updates, login, disable and password
changes, preventing stale aggregate saves from restoring old credentials or status.
Login failures commit rate-limit counters before returning an authentication failure.

Architecture tests enforce dependency direction, prevent query/controller access
to aggregate repositories and separate query and write capabilities. Domain tests
exercise invariants and aggregate identity. PostgreSQL HTTP integration tests cover
migrations, transaction rollback, authorisation and revocation.

## Test organisation

Test packages mirror production packages under `src/test/java`. Command handler
integration tests live beside the corresponding command package, such as
`identityaccess.application.command.login.LoginHandlerIntegrationTests`. Query tests
likewise mirror their dedicated use-case package, such as
`identityaccess.application.query.authenticatesession`. Domain
aggregate, policy and value-object tests use their respective domain packages;
HTTP boundary tests use `identityaccess.presentation`.

`shared.infrastructure.IdentityIntegrationTestSupport` provides test-only database
reset, administrator setup and HTTP helpers. Concrete tests inherit this fixture
without pooling unrelated use cases into one context-wide test class. The shared
PostgreSQL configuration also stays in test-only `shared.infrastructure`. Root
application smoke tests and project-wide architecture tests remain at the root.

## Type-safe persistence

Flyway SQL migrations are the schema source of truth. The Maven jOOQ generator
reads them in migration order and produces typed table, column and record classes
under `shared.infrastructure.jooq`. These generated types never enter domain,
application or presentation code. Infrastructure translates generated database
records to domain aggregates and uses the typed DSL for all database queries and
mutations, including conflict handling and rate-limit expressions.

Builds regenerate sources automatically without connecting to a database; HTTP
integration tests validate the generated queries against PostgreSQL. Architecture
checks reject jOOQ's plain SQL methods and dynamic table/field lookup APIs.
PostgreSQL advisory-lock built-ins use typed, infrastructure-local function
signatures rather than SQL templates.

## Focused persistence implementations

Related read and write contracts share one infrastructure store, while application
handlers continue to depend on separate, narrow interfaces:

| Implementation | Contracts and responsibility |
| --- | --- |
| `JooqAccountStore` | `AccountRepository` and `AccountReader`: load, lock, save and search accounts |
| `JooqAccessStore` | `AccessReader` and `AccessWriter`: role/permission queries, changes and access locking |
| `JooqSessionStore` | `SessionReader` and `SessionWriter`: session lookup, creation and revocation |
| `JooqLoginAttemptWriter` | Login throttling, failure counters and login locking |
| `JooqIdentityAuditWriter` | Append identity audit records |

A shared implementation does not expand the capabilities available to a handler:
queries still receive reader interfaces only. Unrelated persistence responsibilities
remain in separate stores.

`AccountRecordMapper` shares the pure mapping from generated account records to
an aggregate. Implementations do not delegate unrelated work to a shared store.
Commands receive only the contracts they use: audit-only account operations do
not receive role-management capabilities, and session writes do not expose
login-attempt tracking. Login locks the account through its domain repository.
All implementations use the same transaction-aware `DSLContext`; the command's
`UnitOfWork` commits or rolls back their changes together. An integration test
verifies rollback across account, access, audit, session and login-attempt writes.

## Application authorization

Protected application handlers invoke `application.authorization.Authorization`
before accessing protected data or changing state. `application.contract.CurrentActor`
provides the trusted caller's account and session identity. The
`SpringSecurityCurrentActor` adapter reads verified bearer authentication from
Spring Security; the application has no dependency on Spring or thread-local
security contexts.

Authorization checks the current database session and account status, then loads
live effective permissions. A command's actor ID must match the trusted caller;
passing an administrator ID is not proof of authority. Administrative operations
require `identity.manage`. `GetAccountHandler` also permits the caller's own
profile, so `/api/me` remains available without management permission. A self-service
password change additionally requires the current password. Logout is restricted
to the caller's current session, including when the account has other sessions.

Controller `@PreAuthorize` annotations remain an additional HTTP boundary. A direct
handler call without a trusted caller fails with `UNAUTHENTICATED`. A valid caller
without the required permission receives `FORBIDDEN`. Permission changes and session
revocation are respected even when an existing authentication context is reused.
Access-changing commands authorize after acquiring the shared access-change lock.

Login, session authentication, and empty-store administrator bootstrap remain explicit
exceptions to protected-handler authorization. Internal jobs and other transports
must supply a trusted `CurrentActor` adapter rather than bypass permission checks.

## Session decision

Use opaque, cryptographically random 256-bit bearer tokens with the same
`{ "token": "..." }` login response expected by the frontend. Spring Security's
resource-server support authenticates them through an application query. Only a
SHA-256 hash is stored. Sessions expire after eight hours by default and are
checked on every request. Logout deletes the current session; password changes
and disabling an account delete all of that account's sessions. Enabling an
account does not revive old sessions.

JWTs are not required by the frontend, which treats its token as an opaque string.
This choice avoids signing-key setup and makes immediate revocation straightforward.
It trades fully stateless token verification for a database lookup per request.
The existing PHP JWTs and sessions are not accepted by this separate application.

## Identity decision

Require a stable username, a unique email and a display name. Normalise usernames
and emails to lowercase; allow duplicate display names. Accounts exist independently
of People records so administrators and staff do not require a church membership
record. `personId` is currently null for compatibility with the existing response
shape; linking it is part of the People phase.

Email is account identity/contact data at this stage, not proof of ownership.
Email verification, email-based password recovery, MFA, member-portal access and
migration of existing PHP account data are separate follow-up work. There are no
member-portal endpoints in this phase. Migration V2 adds People permissions to the
administrator role used by bootstrap.

## People context

People uses immutable person, household and relationship models. A person profile
contains optional title, names, birth date, gender and an independent life status.
Church membership is a closed four-value enumeration; titles are an extensible
reference catalogue. Age categories are projections evaluated for a date, never
persisted roles or permanent flags. Unknown age stays unknown. `PEOPLE_TIME_ZONE`
controls today's church-local date; occurrence-specific consumers should supply
the occurrence date explicitly.

A household is a grouping, not a legal family relationship. Household membership
is an association with an identity and a validity period, so a child can appear
in two households and old membership records remain available. There is at most
one primary household per person. The primary contact references a membership
rather than an arbitrary person ID; a composite FK enforces household identity,
and aggregate behavior checks current validity. Ending that membership clears the
contact in the same transaction. Contact status has no guardianship significance.

Parent and guardian facts are directed; spouse, sibling and OTHER facts are
canonical symmetric pairs. Inverse relationships are derived on reads. All
relationship writes acquire a database guard row; cycle checking and insertion
share its transaction. Lock multiple persons in UUID string order, which also
matches PostgreSQL's UUID ordering for symmetric canonicalization. This strategy
prevents concurrent reciprocal parentage from passing separate graph snapshots.
There is no fixed parent count and no automatic guardian/pickup inheritance.

Association commands take effect now, after locking. Historical records use
half-open timestamp periods. PostgreSQL exclusion constraints reject overlapping
duplicates and simultaneous primary households. jOOQ DDLDatabase ignores only
PostgreSQL-specific extension/partial/exclusion statements; Flyway executes them
and PostgreSQL integration tests verify them. Backdated or scheduled changes would
require a reviewed interval-aware cycle policy before exposing date inputs.

Person contact methods have one canonical storage location; account contact data
is independently owned by Identity Access. Email values are normalized but can
be shared by different people. Contact and address history belongs to the person
aggregate's revision boundary. Household membership/contact writes use the
household revision. Stores perform version-conditional updates, and handlers keep
writes and auditing in one UnitOfWork. Ending associations preserves history;
archival hides a person from default directory searches without deleting identity.

`JooqPersonStore`, `JooqHouseholdStore`, `JooqRelationshipStore` and `JooqTitleStore`
combine related repositories/writers and reader contracts. Read use cases receive
only readers. Stores use generated table/record classes exclusively. Application
handlers require the published Identity Access `PermissionChecker` contract;
Identity Access's Authorization implements it using trusted live session and
permission checks. People never reads Identity Access persistence or aggregates.

The API remains an administration directory. People read/manage permissions do
not grant access to Children Ministry medical/support records or authorize
pickup. Frontend integration, PHP migration and linking accounts to person IDs
need explicit follow-up work.

## Children Ministry context

Children Ministry owns participation, safeguarding, scheduling and attendance;
it references People IDs without duplicating identity or storing a permanent
"child" flag. `PersonDirectory` publishes the small identity projection and a
person-lock capability. `AccountDirectory` publishes active-account checks.
These contracts are implemented in their owning contexts; Children Ministry
never reads another context's tables or aggregates directly.

Each command/query has its own package. Domain records and policies are immutable
and framework independent. Focused participation, safeguarding, session,
attendance and support stores each combine their related reader and aggregate
repository contracts using generated jOOQ types. Queries receive readers only.
Presentation interfaces contain transport/documentation annotations; controllers
invoke handlers and apply an additional HTTP permission boundary.

Programme enrolment and dated class placement are separate from session capacity.
A session owns its class, room, schedule, seat limit, staffing and lifecycle.
Staff clearance is an explicit evidenced person/account association. Capacity is
the minimum of session seats, room capacity and qualified present staff ratio;
a qualified present leader and the configured minimum staff are also required.
No regulation or jurisdiction-specific staffing policy is inferred.

Verified guardian authority, versioned programme consent and collector grants
are separate records with evidence, validity and revocation. Neither People
relationships nor households establish them. New consent policies invalidate
old consent for future admissions without deleting prior evidence. Attendance
segments retain the enrolment, class placement and consent used at each admission.
Handover independently revalidates collector authority, adult identity and label;
it does not demand current enrolment or consent after a child has arrived.

Child/person locks serialize participation, safeguarding and handover changes.
Person locks use UUID order. Session locks serialize capacity checks and arrival
writes; transfers lock source and destination sessions in UUID order. PostgreSQL
exclusion/partial-unique constraints also prevent overlapping placements, room or
class sessions, and duplicate open attendance. Optimistic revisions protect edits.
Every successful mutation and its metadata audit share one UnitOfWork.

Pickup codes are random 128-bit credentials, returned once and hashed at rest.
Replacements/transfers invalidate prior labels. Failed validation returns a
transaction result so its attempts/audit commit before the error is raised.
Supervised lost-label collection bypasses only the label and requires a reason.
Arrival and checkout have caller-bound request identities for retry handling.
Sensitive payloads and codes have redacted textual representations and never
appear in attendance read projections or ordinary People profiles.

Care access is an explicitly audited command requiring support permission and
session scope (or management permission). Retention purge is a bounded authorized
command; it rechecks expiry under the child lock before deleting. Audit records
retain action/actor/target/time rather than medical payloads. GET queries are
read-only. Future background cleanup must provide a trusted actor explicitly.

Sessions retain their own schedule and integrate with Volunteering through published
application contracts. Events owns general church gatherings and provides a separate
activity adapter. Each context keeps its domain model and stores private.
There is no import of PHP children or people data.

## Children Ministry modules and Communications

The implemented ownership and integration map is documented in `CONTEXT-MAP.md`.
Children Ministry now groups its Clean Architecture layers inside cohesive
internal modules. Communications is a separate bounded context owning reusable
email delivery and configurable versioned templates. Children Ministry publishes
a dispatch eligibility adapter; Communications never imports ministry code.
The originating context retains recipient authority and live approval checks.
Published template revisions are immutable, and approved deliveries hold frozen
content rather than references that are rerendered at dispatch.

## Events scheduling and registration

See `CONTEXT-MAP.md` for ownership and lock order. Events separates metadata,
recurrence series and stable occurrences. Date-bounded, paginated read contracts
keep calendar queries away from write repositories. Preview commands are actually
read-only queries; applying a review is a separate versioned command with a hash
that binds its scheduling state and registration policy revisions. Explicit
reschedule, cancel and restore handlers enforce current actor permissions directly.

Focused jOOQ stores implement cohesive read/repository contracts using generated
Flyway types. PostgreSQL timestamps retain microsecond precision; occurrence IDs
survive changes. Exclusive venue exclusion constraints, per-series origin uniqueness
and per-occurrence person/queue uniqueness defend persistence invariants. Registration
and waitlist changes use targeted occurrence locks and set-based updates with
append-only histories, rather than replacing parent collections.
