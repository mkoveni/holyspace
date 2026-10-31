# OneChurch Java API

Independent Spring Boot API foundation using Java 25, Maven, jOOQ, PostgreSQL 18,
and Flyway. Spring Initializr generated the project with Spring Boot 4.1.2.
The Spring Boot parent manages library versions.

Included dependencies: Spring MVC, Bean Validation, Actuator, jOOQ, the PostgreSQL
JDBC driver, Flyway with PostgreSQL support, and Spring Boot testing with
Testcontainers. JPA/Hibernate ORM is not used.

## Run with Docker

From this directory:

```sh
docker compose up -d --build
curl http://localhost:8082/actuator/health
```

The API uses port 8082. Its independent development database is available on
`localhost:5433`, named `onechurch_java`, with user `onechurch` and development
password `development-only`. Override `DB_PASSWORD` to change the password when
initialising a new volume. The existing Symfony database and frontend are not
connected to this application.

```sh
docker compose down
```

This stops the application and preserves its database volume.

## Run with a local JDK

Install JDK 25. The Maven wrapper downloads Maven; a separate Maven installation
is unnecessary. Start only the development database, then the application:

```sh
docker compose up -d database
./mvnw spring-boot:run
```

Configuration is supplied through `DB_URL` (a JDBC URL), `DB_USERNAME`,
`DB_PASSWORD`, and `SERVER_PORT`. Defaults target the separate local development
database above. The health endpoint exposes status without database details.
Authentication and identity management are implemented. See the identity API below.

## Verify

With JDK 25 and Docker running:

```sh
./mvnw verify
```

Integration tests start a disposable PostgreSQL 18 instance with Testcontainers.
They apply the production Flyway migrations, read the resulting tables through
generated jOOQ types, and verify the HTTP health endpoint. They never use the development database. Tests fail
if Docker is unavailable; they are not silently skipped.

Tests mirror the production package structure. Each command's and query's integration tests
live in the corresponding use-case package; domain and value-object tests live in their domain
packages. Shared PostgreSQL and HTTP fixtures are under the test-only
`org.onechurch.shared.infrastructure` package.

## Code formatting

Java production and test sources use four-space block and continuation indentation
and a 120-character line limit. Spotless applies the checked-in Eclipse profile
in `eclipse-formatter.xml`. Use explicit class and static imports;
wildcard imports are rejected. Use braces for control-flow bodies, including
single statements. `.editorconfig` records indentation and whitespace conventions.
Generated jOOQ files are excluded from formatting.

```sh
./mvnw spotless:apply
./mvnw spotless:check
```

`./mvnw verify` also checks formatting and imports during `validate`. The Maven
JVM configuration includes the compiler-module exports needed by import cleanup
on Java 25; the Docker build copies the same configuration.

## Schema changes and jOOQ

Production migration files belong in `src/main/resources/db/migration` using
Flyway names such as `V2__create_people.sql`. `V1__identity_access.sql` creates the
identity tables and initial administrator role/permission. It creates no accounts.
Flyway runs on application startup; automatic baselining and database cleaning
are disabled. Do not point this application at the Symfony database before an
explicit schema ownership and baseline plan exists.

Maven generates jOOQ table and record classes from the production Flyway SQL files
in Flyway order during `generate-sources`. The generator uses `DDLDatabase`, so
compilation does not require a running database. `verify` also exercises the schema
and queries against actual PostgreSQL with Testcontainers.

```sh
./mvnw generate-sources
./mvnw clean verify
```

Generated classes are placed in `target/generated-sources/jooq` under
`org.onechurch.shared.infrastructure.jooq` and are recreated on a clean build.
Do not edit or commit them. Maven registers them as compilation sources. Docker
builds run the same generation lifecycle before compiling.

Inject Spring's configured `DSLContext` into persistence implementations. Use only
generated table/column references and jOOQ's typed query methods for selects,
joins, inserts, updates, deletes and upserts. Map generated records to domain
aggregates inside infrastructure. Plain SQL execution and string-based table or
field lookups are forbidden by the architecture tests. PostgreSQL advisory locks
use isolated typed function signatures because those built-ins are not part of
the application's generated schema.

Keep the modular context structure and dependency boundaries described in
[`../docs/clean-architecture-cqrs.md`](../docs/clean-architecture-cqrs.md): domain,
application, infrastructure, and presentation. Keep jOOQ in persistence implementations;
controllers call application use cases. Maven `verify` is the current test gate;
ArchUnit checks enforce Java dependency boundaries as part of `verify`.
The Java backend uses **clean architecture and Domain-Driven Design (DDD)**.
See [the architecture decision](ARCHITECTURE.md) for bounded contexts, aggregates,
value objects, domain repositories and dependency rules.

## First administrator

Run bootstrap once against the empty Java database. There is no public registration
or bootstrap HTTP endpoint and no default account/password. Supply the password
through a private file rather than a command argument. Use a password of at least
12 characters and at most 72 UTF-8 bytes. Usernames contain 3–80 letters, numbers,
dots, underscores or hyphens. Every account requires email and display name.

With a local JDK, create a private password file, start the database, then run:

```sh
ONECHURCH_BOOTSTRAP_ENABLED=true \
ONECHURCH_BOOTSTRAP_USERNAME=admin \
ONECHURCH_BOOTSTRAP_EMAIL=admin@example.org \
ONECHURCH_BOOTSTRAP_DISPLAY_NAME="Church Administrator" \
ONECHURCH_BOOTSTRAP_PASSWORD_FILE=/absolute/path/to/private-password-file \
./mvnw spring-boot:run -Dspring-boot.run.arguments=--spring.main.web-application-type=none
```

The password file should have mode `600`; one trailing newline is ignored.
Bootstrap refuses to run once any account exists. Start the API normally afterwards
with bootstrap disabled and remove the temporary password file.

For Docker, build the API image and start its database first:

```sh
docker compose build api
docker compose up -d database
docker compose run --rm --user "$(id -u):$(id -g)" \
  -v /absolute/path/to/private-password-file:/run/bootstrap-password:ro \
  -e ONECHURCH_BOOTSTRAP_ENABLED=true \
  -e ONECHURCH_BOOTSTRAP_USERNAME=admin \
  -e ONECHURCH_BOOTSTRAP_EMAIL=admin@example.org \
  -e ONECHURCH_BOOTSTRAP_DISPLAY_NAME="Church Administrator" \
  -e ONECHURCH_BOOTSTRAP_PASSWORD_FILE=/run/bootstrap-password \
  api --spring.main.web-application-type=none
docker compose up -d api
```

## Identity API

Sign in using a username **or email** in the `username` field:

```http
POST /api/login_check
Content-Type: application/json

{"username":"admin@example.org","password":"your-password"}
```

The response is `{"token":"..."}`. Send it as `Authorization: Bearer <token>`.
Tokens are opaque, not JWTs. Set `SESSION_LIFETIME` to change the default `8h`
expiry (allowed range: one minute to one day). Login allows five failed attempts
per normalised identifier per minute, backed by PostgreSQL across API instances.
The frontend may use its same-origin proxy or connect directly using the CORS
configuration below.

| Endpoint | Access and request |
| --- | --- |
| `POST /api/login_check` | Public; username/email and password |
| `GET /api/me` | Signed in; returns identity, roles and effective permissions |
| `POST /api/logout` | Signed in; revokes this session |
| `PUT /api/me/password` | Signed in; `currentPassword`, `password`; revokes all own sessions |
| `GET /api/users?search=&limit=50` | `identity.manage`; account directory (`items` array), limit 1–100 |
| `POST /api/users` | `identity.manage`; `username`, `email`, `displayName`, `password`; returns `id` |
| `GET /api/users/{id}` | `identity.manage`; account details |
| `PUT /api/users/{id}` | `identity.manage`; `email`, `displayName`; username remains stable |
| `GET /api/users/{id}/permissions` | `identity.manage`; effective permission codes |
| `PUT /api/users/{id}/password` | `identity.manage`; `password`; revokes all target sessions |
| `POST /api/users/{id}/disable` | `identity.manage`; disables account and revokes all sessions |
| `POST /api/users/{id}/enable` | `identity.manage`; enables account without restoring sessions |
| `GET /api/roles` | `identity.manage`; role catalogue |
| `POST /api/roles` | `identity.manage`; `name`, optional `description` |
| `GET /api/permissions` | `identity.manage`; permission catalogue |
| `POST /api/permissions` | `identity.manage`; `code`, optional `description` |
| `POST/DELETE /api/users/{id}/roles/{roleId}` | `identity.manage`; assign/remove a role |
| `POST/DELETE /api/roles/{roleId}/permissions/{permissionId}` | `identity.manage`; grant/remove a permission |

Account responses include `id`, `username`, `email`, `displayName`, `status`,
`personId`, `roles`, `permissions`, `createdAt` and `updatedAt`. The directory returns
summary records with empty role/permission arrays; fetch individual accounts for
access details. Password hashes and session hashes are never returned. Password
and role changes are audited without storing credentials. The last active account
with `identity.manage` cannot be disabled or stripped of that permission.

Permissions come from assigned roles. `context.manage` implies `context.read`;
volunteering management also implies serving, ministries management implies
leading, and follow-up management implies working follow-ups. No role name grants
implicit unrestricted access. Newly created accounts have no roles by default.

Errors return `{"message":"..."}`: 400 invalid input, 401 invalid credentials or
session, 403 insufficient permission, 404 missing account/role/permission, 409
conflict, and 429 login throttling. Successful mutations without a response body
return 204. Account, role and permission creation return 201 with an `id`.

This phase does not migrate PHP accounts, add MFA/email recovery, implement People
linking, or switch the current frontend. The Java API's required `email` and
`displayName` fields must be added to its frontend account form when connecting it.

## Application authorization

Permissions are enforced in application handlers as well as HTTP controllers.
Protected direct calls require a trusted authenticated caller, a valid current
session, an active account, and current database permissions. Command actor IDs
must match that caller; an administrator ID in a command does not grant access.

Administrative use cases require `identity.manage`. Reading the caller's own
profile and changing their own password remain self-service operations; password
changes require the current password. Logout revokes only the caller's current
session. Revoked permissions, disabled accounts, and expired or deleted sessions
are rejected even if the calling process still holds an authentication context.

The framework-independent application authorization service uses `CurrentActor`;
Spring Security is confined to its infrastructure adapter. Direct calls from jobs
or other transports do not receive automatic administrator privileges. Public
login, session authentication, and the existing empty-database bootstrap retain
their separate entry-point rules.

## Browser access (CORS)

API routes under `/api/**` accept browser requests from `http://localhost:5173`
and `http://127.0.0.1:5173` by default. Set `CORS_ALLOWED_ORIGINS` to a
comma-separated list of exact frontend origins to replace these defaults:

```sh
CORS_ALLOWED_ORIGINS=https://portal.example.org,https://staff.example.org docker compose up -d --build api
```

Origins include the scheme and port, without a path or trailing slash. Set the
variable to an empty string to disable cross-origin API access. Compose passes
this setting through to the API container.

Preflight requests are handled before authentication. Requests can send
`Authorization`, `Content-Type`, and `Accept` headers; responses expose
`WWW-Authenticate`, `Retry-After`, and `Location`. The preflight cache lasts one
hour. Authentication uses bearer tokens; cookie credentials are disabled.
Authentication and permission checks still apply to the actual API request.
The `/docs` page and specification use the API's own origin.

## API reference

Start the API and open [Scalar API reference](http://localhost:8082/docs) for a
searchable OpenAPI 3.1 reference with schemas, request examples, and an interactive
request client. springdoc generates the document from API interfaces and request
validation annotations:

- [JSON specification](http://localhost:8082/v3/api-docs)
- [YAML specification](http://localhost:8082/v3/api-docs.yaml)

To try protected operations, call `POST /api/login_check` with a username or email
and password, copy the returned `token`, and paste it into Scalar's bearer-token
field. Use the token itself; the client adds the `Bearer` prefix. Account and
access administration requires `identity.manage`. Session tokens are opaque and
are revoked by logout, password changes, and account disabling.

The documentation is publicly readable by default; API authentication and
permissions still apply when trying requests. Set `API_DOCS_ENABLED=false` to
disable both Scalar and the JSON/YAML endpoints. The API's configured port also
applies to the documentation URLs above.

Documentation configuration stays in `shared.infrastructure.documentation`.
Interfaces in each context's `presentation.api` package own routes, request/response
records, validation, and OpenAPI descriptions. Controllers implement these
interfaces and contain only endpoint behavior and runtime permission enforcement. Keep
success status codes, errors, and permission requirements aligned with behavior.

## People API

The Java People context now supports person profiles, canonical contact methods,
address history, households, household memberships and family relationships.
All writes use Flyway-managed PostgreSQL tables and generated jOOQ types.
`people.read` permits reads; `people.manage` permits writes and implies reads.
Migration V2 grants both to the existing administrator role. New roles receive
neither automatically. Application handlers enforce these permissions even when
called without an HTTP controller.

Membership types are exactly:

| API value | Display label |
| --- | --- |
| `ACTIVE_MEMBER` | Active Member |
| `REGULAR_VISITOR` | Regular Visitor |
| `VISITOR` | Visitor (default) |
| `TRANSFERRED` | Transferred |

Titles are optional catalogue codes. Seeded titles include Dr, Mr, Miss, Mrs, Ms,
Prof, Rev, Ps and Apost. `POST /api/people/titles` adds another title with
`{"code":"PASTOR","label":"Pastor"}`. Titles confer no identity or family role.
`GET /api/people/metadata` returns the current catalogue and supported classifications.

Register a person using `POST /api/people`:

```json
{
    "person": {
        "titleCode": "DR",
        "firstName": "Solly",
        "lastName": "Mabasa",
        "dateOfBirth": "1980-04-12",
        "gender": "MALE",
        "lifeStatus": "LIVING"
    },
    "membershipType": "ACTIVE_MEMBER"
}
```

Only first and last names are required. Omitted gender/life status become UNKNOWN;
omitted membership becomes Visitor. DOB can be absent, but cannot be in the future.
A death date requires DECEASED life status and cannot precede birth or be in the
future. Neither deceased status nor archival changes church membership.

`GET /api/people/{id}?asOf=2026-10-25` returns a profile, age classification,
contact/address history and version. Age is CHILD below 18, ADULT from the 18th
birthday, and UNKNOWN when birth date is missing or after the requested date.
An omitted `asOf` uses today's date in `PEOPLE_TIME_ZONE` (default
Africa/Johannesburg). This is classification only; future Children Ministry
eligibility must also evaluate enrolment, consent and occurrence-specific rules.

Search with `GET /api/people?search=Solly&membershipType=ACTIVE_MEMBER&limit=50&offset=0`.
Add `ageCategory=CHILD&asOf=2026-10-25` to find children as of an occurrence date
using a date-of-birth range query. Leap-day birthdays use February 28 in non-leap
years, consistently in searches and profiles. Search responses are summaries
without contact/address collections. Archived
people are excluded unless `includeArchived=true`. There is stable name/id
ordering and a 100-item limit. Person updates use `PUT /api/people/{id}` with
`expectedVersion`, `person` and `membershipType`. `PUT /api/people/{id}/archive`
accepts `expectedVersion` and `archived` for reversible archival.

Contact methods use `POST /api/people/{id}/contacts` with `expectedVersion`,
`kind` (EMAIL/PHONE), `value` and `primary`. Email values normalize to lowercase;
shared values across different people are allowed. Setting primary clears the
previous primary for that kind. Contact methods and addresses end using their
`/{contactId}/end` or `/{addressId}/end` endpoints with `expectedVersion`; historical
values remain visible to authorized People readers. Every contact/address change
increments the person's version. The documented address payload includes street,
city, region, postal code and a two-letter country code.

Households use `/api/households`. Create with `{"name":"Mabasa household"}`;
add a member at `/{id}/members` with `expectedVersion`, `personId` and `primary`.
People may belong to several households; only one can be primary at a time.
`PUT /{id}/primary-contact` accepts the household `expectedVersion` and a current
`membershipId` (or null to clear). End a membership with
`POST /{id}/members/{membershipId}/end`; this also clears its primary contact.
Read household history at `GET /api/households/{id}` or
`GET /api/people/{id}/households`. Every household mutation increments its version.
Changing an existing primary household is an explicit end-and-rejoin operation.

Relationships use `POST /api/relationships` with `sourceId`, `targetId` and `type`:
PARENT_OF, GUARDIAN_OF, SPOUSE_OF, SIBLING_OF or OTHER. The first two are directed;
the remaining types are symmetric and stored once. `GET /api/people/{id}/relationships`
derives inverse CHILD_OF and WARD_OF views. End a relationship with
`POST /api/relationships/{id}/end` and its `expectedVersion`. Relationships and
household memberships begin/end now, retain history, and use half-open periods;
backdating/scheduling is deliberately outside this API's current temporal policy.

PostgreSQL exclusion constraints reject duplicate overlapping memberships,
conflicting primary households and duplicate relationship facts. The Flyway user
must be able to install the trusted `btree_gist` extension, or it must be installed
before migration. A shared graph lock serializes relationship commands to prevent
concurrent parentage cycles. This favours correctness at church-directory scale;
large graph workloads can later replace the global lock with a reviewed strategy.
Stale writes, duplicates and overlaps return 409, with the whole write and its
audit rolled back. Primary contact is checked in the domain and tied to the
household's membership by a composite foreign key.

These are new Java endpoints; the PHP frontend and data have not been switched or
migrated. They are not a drop-in replacement for the PHP People payloads. Person
accounts remain independent; education/employment, household addresses, sensitive
child-support records, ministry enrolment and pickup authorization are owned by
Children Ministry rather than inferred from this directory model.

## Children Ministry API

The new Java context is available under `/api/children`, documented at `/docs`.
It starts with new records; no PHP data is imported. The implementation follows
these stages:

1. Programmes, immutable consent-policy versions, classes, dated enrolment and
   class placement, verified guardian authority and evidenced consent.
2. Locations, rooms, scheduled sessions, explicit worker clearance and presence,
   and capacity derived from room seats and the programme's staffing policy.
3. Explicit collector authorizations, including validity, revocation and history.
4. Attendance, pickup labels, supervised exceptions, transfers, and restricted
   care information with review/retention dates and audited access/purge.

The API is ready for a Children Ministry frontend; this change does not add those
screens. People remains the source of person identity, names and birth dates.
Household membership and a People guardian relationship confer no pickup rights.
Workers require a verified association between a People person and an active
account, with evidence and screening/training dates. This is a ministry clearance
assessment, not an inferred volunteering assignment. Events and Volunteering can
later integrate through published contracts; sessions currently own their schedule.

| Permission | Capability |
| --- | --- |
| `children.read` | Programme, enrolment and session administration reads |
| `children.manage` | Programme, placement, room, session and staffing changes; implies read |
| `children.safeguarding` | Verify/revoke guardian authority, consent, worker clearance and pickup grants |
| `children.station` | Assigned-session rosters, check-in, label issuance and ordinary checkout |
| `children.supervise` | Session-scope override, transfers and documented lost-label checkout |
| `children.support` | Explicit access to sensitive care information |

Flyway V3 grants these permissions to the administrator role. Other roles receive
none automatically. Station operators must also be present on the session roster;
supervisors may work across sessions. Every handler checks the trusted caller and
live permissions. Care changes require both manage and support; access without a
session requires manage and support. Retention purge requires support and supervise.

Create a programme, publish its policy, add classes, enrol children and assign
classes. Record guardian evidence and consent against the exact policy version.
Create a location with an IANA time zone, rooms, cleared workers and a session;
assign a present leader and the required staff, then open it. `GET
/api/children/sessions/{sessionId}/expected-children` supplies the planned station
roster. Programme enrolment does not reserve a session seat. Current staffing,
clearance, room capacity, consent, class placement and age are checked at admission.
Programme minimum staff and children-per-staff values are explicit local policy,
not a claim of regulatory compliance.

Enrolment/class-placement dates are half-open: `validFrom` is included and
`validUntil` is excluded. Consent and authority must cover admission through the
session end. Instant inputs normalize to PostgreSQL microsecond precision. A new
policy version requires fresh consent; old evidence remains available. Missing
birth dates never establish child/adult eligibility.

Check in with `POST /api/children/attendance`, providing `sessionId`, `childId` and
a client-generated UUID `requestId`. Retrying the same request returns the same
attendance; a child cannot hold two open attendances. Then call `POST
/api/children/attendance/labels` with `attendanceId`, `expectedVersion` and
`replacementReason` (empty for first issue). This returns a 128-bit pickup code
once. Store it only on the collector's label; reads never recover it, and the
database stores only its hash. A replacement requires a reason and invalidates
the old code. Lost issuance responses require an explicit replacement.

Checkout uses `/attendance/checkout` with attendance/version, request UUID,
collector ID, `identityVerified: true` and the pickup code. Current collector and
guardian authority are checked again; a code never replaces identity verification.
Failed codes commit their audit and attempt counter before returning an error.
Reload attendance after a failed attempt; five failures throttle code attempts
for 15 minutes. Labels expire after 24 hours. Supervisors may use
`/attendance/supervisor-checkout` with a reason to bypass the label only: collector
authorization and identity verification remain mandatory. Matching checkout retries
are idempotent. Ended enrolment or changed consent does not prevent safe checkout.

Transfers use `/attendance/transfers`, revalidate target admission and capacity,
retain attendance segments/evidence, and invalidate the pickup label. Issue a new
label in the destination. Session closure/cancellation requires no open attendance.
Mutation bodies use optimistic `expectedVersion`; stale edits return 409.

Sensitive support data is excluded from People profiles and ordinary rosters.
`POST /support/access` is an audited access command; ordinary GET queries never
write audit records or perform cleanup. Records past `retainUntil` cannot be read.
An authorized caller can invoke `/support/purge` with a bounded `limit` to remove
expired payloads while retaining metadata-only audit history. Purge is explicit;
no background scheduler is enabled by this change.

All new HTTP integration tests use REST Assured with per-request configuration
and the actual Spring Boot server backed by Testcontainers PostgreSQL. Existing
Identity/People fixture-based HTTP tests now use the same REST Assured transport.

Ending an enrolment also ends its remaining class placements in the same
transaction. Re-enrolment creates a new enrolment/placement identity and preserves
the old records. A worker cannot be marked present in overlapping sessions;
mark them absent in the previous session before moving them.

### Station discovery and names

`GET /api/children/station/sessions` lets station staff discover their present
session assignments, with date windows and pagination applied inside the scoped
SQL query. Supervisors can discover all sessions. `GET /station/sessions/{id}`
and `GET /station/sessions/{id}/attendance` (under `/api/children`) enforce the
same assignment/supervisor scope. Named attendance returns attendance and child
names without granting People directory access or exposing birth dates, support
payloads or pickup hashes. The Java frontend uses these endpoints for station
operations; administrative session endpoints retain `children.read` protection.

### Programme enrolment roster

`GET /api/children/programmes/{programmeId}/children` requires `children.read`.
It returns `{ children, total }`, with child names, enrolment dates and status,
and current class placements. The roster contains no safeguarding, care or pickup
information. Names are resolved through the published People directory contract.

Use `search` to match a child's name across the programme, `status` (`ALL`,
`ACTIVE`, `UPCOMING`, `EXPIRED`, or `ENDED`), and `limit`/`offset` for pagination.
Dates use the church time zone and exclusive validity ends. The UI displays the
last included day. Ended enrolments remain visible as history, including when a
child later re-enrols. Totals count enrolment records rather than unique children.

### Child preparation checklist

`GET /api/children/programmes/{programmeId}/children/{childId}/readiness`
requires `children.read`. It returns a church-local, current-time preparation
snapshot: child eligibility, current enrolment, age-appropriate class placement,
published policy, verified guardian authority, current policy consent, and an
available adult collector with valid authority. The response contains completion
flags and the current enrolment; guardian identities, evidence and care payloads
are excluded. Active roster rows include a compact readiness summary.

This snapshot does not guarantee admission to a future session. Session check-in
still evaluates its dates, permission coverage through the session end, staffing
and capacity. Care information is optional and is only retrieved through the
existing explicitly authorized, audited care action. Readiness GET requests do
not load care records or mutate data.

### Session-day station

`GET /api/children/station/sessions/{sessionId}/day` requires `children.station`
and a present staff assignment or supervisor scope. Use `view=EXPECTED`,
`ATTENDING`, or `HISTORY` (collected visits), with `search`, `limit` and `offset`.
Search applies to the entire selected view before pagination; totals reflect the
filtered view. Preparation counts shown in the UI refer to the current page.

Expected children are checked against the session's local date and consent and
guardian authority through its end. Planned-session preflight assumes arrival
at session start; an open session is assessed at the current time. Current
attendance remains accessible for collection even when admission requirements
later change. The endpoint returns no care payloads or guardian evidence.
Check-in remains authoritative and transactional; its response starts the
explicit pickup-label confirmation step. Pickup codes remain in memory only.

### Session closeout

Coordinators use **Closeout review** on a session to see all visits, distinct
children, collections, transfers out and children still awaiting collection.
`GET /api/children/sessions/{sessionId}/closeout` requires `children.manage` at
both the HTTP boundary and the application handler. Totals cover all persisted
attendance segments independently of roster filters and pagination. A transfer
remains in the source session's history after collection at the destination.

Warnings flag missing/expired labels and unsuccessful code attempts for children
still present. The attending roster identifies affected children, while collected
rows identify supervisor exceptions. Counts expose no care details, codes,
collector identity or exception reasons. A coordinator confirms the final room
check and provides a closeout note; the existing status command checks occupancy
again under the session lock and rejects stale revisions. Collection remains
available after the arrival window ends. Closed sessions retain their summary.

### Attendance history and reporting

Open **Children Ministry → Attendance & reports** to filter room visits by
inclusive church-local dates, programme, location, child name, child ID and
outcome. Date ranges cover up to 366 days. Global totals are calculated before
pagination; a child visiting two rooms counts as one child and two visits.
Filters apply to each room arrival, rather than the session start or later
collection date. Outcomes reflect the current attendance record.

`GET /api/children/attendance-reports` requires `children.read`. Read projections
use generated jOOQ types and a read-only repeatable-read transaction so totals
and page rows share a database snapshot. Reporting indexes cover visit arrival
and session history. No People persistence is accessed; identity search and
names use the published PersonDirectory contract.

`GET /api/children/attendance/{attendanceId}/timeline` returns chronological,
paginated room visits for the entire attendance journey, independent of report
date filters. **View journey** is available on report rows and the child's
existing all-time, paginated attendance history. Transfers remain in their
source rooms, with collection recorded only in the final room.

`GET /api/children/attendance-reports/export` requires both `children.read` and
`children.manage` (management already implies read in the access policy).
Exports use the applied report filters and include every matching visit up to
10,000, rejecting larger results without a partial file. CSV includes ISO UTC
times and the location time zone, quotes cells and escapes spreadsheet formulas.
Codes, hashes, collector identities, private care, and exception reasons are
excluded from reporting and exports. Supervisor collections expose only an
indicator. The API applies authorization directly in every query handler as
well as at the HTTP boundary.

### Recurring session scheduling

Use **Repeat session** on a session or session card, then review the resulting
series under **Recurring schedules**. A source session supplies the class, room,
seat limit and assigned team. Choose a first local start/end, repeat every 1–4
weeks, and plan 1–52 dates within 366 days. The preview includes holidays that
can be explicitly skipped, room/class clashes and staffing/clearance warnings.
All repeats keep the location's local weekday and wall time through daylight
saving. Ambiguous and nonexistent wall times are rejected rather than silently
shifted. Overnight repeats are not supported in this version.

The source version is checked again when creating the series. Every included
session is saved as PLANNED in one transaction, with copied team members marked
absent. Clearance warnings permit advance planning; opening each session still
requires a qualified present team and sufficient capacity. All dates are
revalidated at creation; any concurrent room/class conflict rolls back the
complete series and its audits. Actor-scoped request IDs make creation retries
idempotent and reject a changed draft. The stored recurrence and session links
retain the series history. This is a finite planning horizon, with no implicit
background generation or automatic opening of sessions.

Managers can cancel remaining future PLANNED dates with a version and reason.
Sessions are locked in UUID order, occupancy is checked, and past, OPEN,
NEEDS_REVIEW, CLOSED and already-cancelled sessions retain their state. Sessions
may also be managed individually using their ordinary status controls. To plan
the next term, repeat an appropriate existing session again.

Endpoints: POST `/api/children/session-series/preview`, POST
`/api/children/session-series`, GET `/api/children/session-series`, GET
`/api/children/session-series/{id}`, and POST
`/api/children/session-series/{id}/cancel`. Reading requires `children.read`;
preview, creation and cancellation require `children.manage`. Application
handlers enforce live permissions independently of the HTTP boundary. All SQL
uses generated jOOQ types; related readers and writers share JooqSessionSeriesStore.

### Team planning and readiness

`GET /api/children/team-planning` provides an upcoming staffing workspace with
programme/location/name filters and readiness totals across the whole selected
scope. It accepts an inclusive date range of up to 90 days and pages of up to 100.
Scopes above 1,000 matching sessions must be narrowed. Readiness includes adult
identity, active linked accounts, screening and training through session end,
leaders, staffing ratios, and overlapping assignments, including absent workers.
Expected capacity uses qualified assigned staff; admission still uses qualified
**present** staff and the existing session-day checks.

Coordinators with `children.manage` can search registered workers through
`GET /api/children/team-planning/workers`, preview a change through
`POST /api/children/team-planning/preview`, and apply it through
`POST /api/children/team-planning/apply`. A draft carries 1–25 distinct session IDs
and their expected versions, a worker, a role, and optionally the worker to
replace. Apply also requires a reason. The transaction locks sessions and workers,
rechecks versions and clashes, saves all selected future PLANNED sessions together,
and audits each change. Replacement must cover every selected session. It never
changes confirmed presence; a new assignment is absent. Clearance warnings allow
advance preparation, but clashes and stale versions reject the entire change.

### Safeguarding follow-up

`GET /api/children/safeguarding-follow-up` requires `children.safeguarding` and
lists missing, expired, or expiring guardian authority, consent for the current
programme policy, and eligible collector permissions for currently enrolled
children. Programme, child name, type, status, and a 1–90 day horizon are supported.
Alternative currently usable evidence is compared before raising expiry alerts;
consent and pickup coverage are capped by the supporting authority. Future-start
renewals do not count as current coverage. Searches and programme filters precede
paging; totals cover the selected scope before type/status filtering. Scopes above
1,000 active child/programme records must be narrowed.

Care-review dates are included only with `children.support`. The query selects
review metadata, excludes payloads beyond retention, and never reads allergies,
care instructions, or restricted notes. Explicit `kind=CARE` requests without that
permission return 403. Private care details remain in the existing authorized,
audited read workflow. The UI links permission follow-ups to the appropriate
profile form and care reviews to the profile's explicit care-access action.

### Incidents and restricted follow-up

The Incident follow-up screen and `/api/children/incidents` record injuries,
illness, behavioural concerns, safeguarding concerns, and other incidents.
`children.incidents.record` permits reporting only in a session where the caller
is assigned and present (or has the existing supervisory scope).
`children.incidents.manage` permits incident coordination independently of ordinary
programme-management or safeguarding permissions. Both permissions are added to
the default administrator; assign them explicitly to other roles.

Reports require the child's actual attendance segment and an occurrence timestamp
within that segment, a category, severity, description, immediate response, review
date, and retention date. Actor-scoped retry keys prevent duplicate submissions
and reject changed payloads. Reports can be entered after the session; private
contents never appear in attendance reports, ordinary People records, or general
audit reasons.

The metadata queue is read-only. Private detail/history access uses explicit
`POST /incidents/{id}/access` and is audited on every successful page read.
Versioned follow-ups assign an active incident coordinator, set a review date,
append actions or verified guardian contact, close with a resolution, or reopen a
closed record. History is append-only. Closed records reject further actions
until reopened. Private details are inaccessible after retention; the bounded,
authorized purge command removes expired descriptions and note payloads while
retaining incident/event metadata and a purge audit. GET requests never purge.

Session closeout returns incident counts only to incident coordinators. Closing
with current unresolved incidents requires incident-management permission and
`acknowledgeIncidents: true`. The session and new-report paths share the session
lock, so closure rechecks the current count. Closing does not resolve incidents,
remove follow-up ownership, or prevent later follow-up. Collection remains
independent of incident closure.

### Guardian communication tasks

The Guardian communications screen and `/api/children/guardian-communications`
require `children.safeguarding`. Prepare a consent-renewal request for the current
programme policy or a reminder for an upcoming session with an active child class
placement. Both use current verified adult guardian authority. Household links do
not authorize contact. Message text is generated from ordinary identity/programme
information and includes no incident or care payload.

This is a **manual communication queue**, with DRAFT, CONTACTED, and CANCELLED
states. Manual contact recording does not send a message or claim delivery success.
Reviewed SMTP delivery and scheduling use the separate email workflow below. Review/copy the draft
and share through an existing channel, then record the actual contact method and
reference. Recording contact does not record consent or change pickup authority.
Draft review/contact recheck guardian validity, current policy and session
eligibility. Stale drafts can be cancelled without pretending contact occurred.
Prepare requests have actor-scoped retry keys; contact/cancellation uses optimistic
versions and preserves the contact audit. Lists contain metadata, with message
text loaded separately on explicit review.

### Reviewed guardian email and scheduling

Guardian email can now be verified, reviewed, queued immediately, or scheduled
within 90 days. The message, recipient contact, verification ID, and communication
version are bound by a review fingerprint. A coordinator records evidence of
verification with the guardian; this is not an automatic email ownership
challenge. Verification expires within a year and can be revoked. Ending or
changing the People contact invalidates it. People contact lookup stays within a
published People contract.

`children.communications.send` plus `children.safeguarding` is required to approve,
cancel or retry email. Default administrators receive the new permissions. Each
communication has at most one delivery record. Saved approval is an explicit
message-scoped delegation to the scheduler, not a background administrator login.
The scheduler checks the approver's active account and both live permissions,
current guardian authority, recipient verification, policy, enrolment and class
placement again before dispatch. Scheduled messages which become ineligible are
BLOCKED. Approval/retry/cancellation and transport events have audit records;
ordinary audit reasons contain neither message body nor email address.

SENDING is committed before SMTP runs. Concurrent workers can claim a delivery
only once. SMTP acceptance becomes SENT. A process interruption, timeout or other
uncertain transport outcome becomes UNKNOWN and is never automatically retried.
A stale SENDING claim becomes UNKNOWN after five minutes. Only definite failures
or blocked messages can be explicitly retried, after revalidation, with a maximum
of three attempts. A retry records the new approver. A stable Message-ID assists
provider reconciliation; SMTP itself does not promise deduplication or delivery.

The provider-receipt API requires a live bearer session with the dedicated
`children.communications.receipts` permission. Configure a trusted provider relay
to authenticate, verify the provider's native event signature, and POST the
correlated delivery ID, exact Message-ID, namespaced unique eventKey, outcome
DELIVERED/FAILED, and provider evidence reference to
`/api/children/email-deliveries/receipts`. This is not an anonymous webhook or a
provider-specific signature adapter. Replayed events are idempotent; conflicting
replays and mismatched IDs are rejected. SMTP alone never confirms delivery.
A trusted provider receipt can reconcile an UNKNOWN outcome. Capture-mode mail
cannot be labelled DELIVERED by a provider receipt.

### SMTP configuration and local test inbox

Standalone API defaults keep email disabled. Configure `EMAIL_ENABLED`,
`EMAIL_FROM`, `SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, `SMTP_PASSWORD`,
`SMTP_AUTH`, `SMTP_STARTTLS`, and `EMAIL_CAPTURE`. Live deployments should use
`EMAIL_CAPTURE=false`, authenticated SMTP and required STARTTLS. Finite five-second
connection/read/write timeouts prevent unbounded network waits. Mail health is
checked only when email is enabled. Credentials belong in deployment secrets,
never committed source.

The development Compose stack enables SMTP against **Mailpit**, with capture mode
on and its inbox at `http://localhost:8025`, bound to loopback. It does not relay
messages to real guardians. Start it with:

```sh
docker compose -f backend-java/compose.yaml up -d mailpit api
```

Reviewed messages appear as "Captured in local inbox" rather than delivered.
Setting production SMTP variables replaces that local connection. Review and
approve each message explicitly; preparing a draft alone never sends it.

### Ministry overview

`/children/overview` combines upcoming sessions for seven church-local dates,
team readiness, 30-day safeguarding follow-up, unresolved incidents and delivery
attention counts. The corresponding GET API is read-only. Restricted sections
are omitted as null unless the caller has the relevant permission, with care
metadata separately support-gated. Counts cover the complete applicable scope;
lists show at most five rows and link to paged workspaces. Large planning/cohort
scopes show a narrowing notice instead of silently truncating totals. Private
incident and care payloads are never opened by the overview.

SMTP uses [Spring Boot mail support](https://docs.spring.io/spring-boot/reference/io/email.html) with finite network timeouts. Capture mode is restricted to local SMTP hosts; it cannot be enabled against an external SMTP host.

### Communications and email templates

Open `/communications/templates` in the Java frontend to create and manage email
wording. The workspace supports rich text formatting, links, named variables,
sample-value previews, drafts, explicit publication, archival and published
revision history. `communications.templates.read` permits browsing and preview;
`communications.templates.manage` permits editing and publication and inherits
read access through the existing permission policy.

Use variables such as `{{guardianName}}` in text or a subject. Declare each
variable used by the template. Preview requires one sample value per declared
variable. Values are escaped text, never executable expressions or HTML. Link
addresses must be fixed HTTP, HTTPS or mailto addresses. The server removes
scripts, styles, images and event handlers. Browser previews run in a sandbox.

Two published starter templates are connected to guardian email review:

- `children.consent-renewal`
- `children.session-reminder`

Both retain `{{message}}` in their body so the originating ministry's required
wording remains present. They may additionally use `guardianName`, `childName`,
`programmeName`, `sessionDate` and `policyName`. Session/policy values are empty
when they do not apply. Required ministry variables are enforced by Children
Ministry's published template-publication adapter; the reusable Communications
context contains no ministry-specific rules.

Saving an edit creates a draft while the previous published revision stays in
use. Publishing activates the new revision for subsequent message reviews.
A changed template invalidates an earlier review fingerprint at approval.
Approval freezes the rendered subject, HTML and template revision in the delivery
record. Dispatch revalidates ministry eligibility without rerendering the content.
Archiving stops new rendering and leaves already approved messages unchanged.
Previewing never saves a record or sends email.

Other contexts use the published `EmailTemplates`, `EmailDeliveries` and
`DeliveryReader` contracts and supply their own `DeliveryEligibility` adapter.
Rendering accepts context-supplied values and selects the declared variables;
missing required values fail. Preview additionally rejects surplus values.
New template keys become usable when a sending workflow binds its own values to
that key. Template creation alone never sends a message.

The ownership and dependency rules are described in [CONTEXT-MAP.md](CONTEXT-MAP.md).
Children Ministry's Participation, Sessions, Safeguarding, Incidents, Messaging
and Reporting modules retain their own Clean Architecture layers and matching
test packages. Architecture tests enforce the Communications boundary and keep
internal module adapters private. The V13 Flyway migration preserves existing
Java delivery IDs, content and history while transferring table ownership.

## Volunteer and team workflows

The Java volunteer workspace is available at `/volunteering`, teams at
`/volunteering/teams`, duty planning at `/volunteering/schedule`, personal
invitations at `/serve`, and reminder delivery status at
`/volunteering/deliveries`. All routes enforce their corresponding live API
permissions. The administrator receives these permissions in migration V14.

Register a People record as a volunteer, create a team and its roles, then record
membership, role approval, credential evidence and availability. Plan a standalone,
Children Ministry or Events duty, or review up to 25 recurring standalone dates in an
explicit time zone before applying them atomically. Proposed assignments reserve
positions; confirmation records commitment independently of readiness. Invitations
are private to the linked login. Coordinators explicitly release expired proposed
holds through the schedule action; read requests never perform maintenance.

For Children Ministry, choose the exact session when planning a shift and map its
role to leader or assistant. In the session's caring-team panel, choose **From
serving plan**. The person must already have a verified Children Ministry worker
record. Paused volunteers, revoked evidence, changed source times and other lost
requirements immediately stop the imported worker from counting as qualified.

Reminder preparation is available on confirmed upcoming assignments. Review the
recipient and rendered email, record verification/permission evidence, then queue
it. The existing email configuration and local inbox apply. The reusable rich-text
email template is managed in Communications. Dispatch blocks stale eligibility;
unknown transport outcomes require investigation before sending another message.

The API is documented under the volunteer tags at `/docs`. Lists are paged in
batches of 1–100, calendar queries cover at most 90 days, and recurring previews
reject missing or ambiguous daylight-saving times. Standalone shifts, Java
Children sessions and Java Events occurrences are supported; PHP data is not imported.
See [context ownership](CONTEXT-MAP.md) for integration and locking rules.

Rescheduling a duty returns confirmed assignments to proposed for renewed
commitment. Existing approvals cannot be repurposed by changing a role from
assistant to leader; create and approve a separate role instead.

## Events, recurrence and registration

Open `/events` for the calendar and event library, and `/events/venues` to set up
places. Create a venue first, choose **Plan event**, enter the event details and
local schedule, then preview and create the reviewed dates. Recurrence is finite:
once, daily, weekly or monthly, up to 104 occurrences within two years. Missing
monthly dates and ambiguous/nonexistent daylight-saving times require correction.

Each date opens its own occurrence workspace. Reschedule this occurrence or this
and future dates; future rescheduling preserves individual overrides. Cancellation
can include future overrides and previews affected booking counts. Restore one
cancelled occurrence explicitly. Dates retain their identity and change history.

Registration settings control opening, capacity and waitlisting. Register an
existing People record. Full dates allocate a FIFO waitlist; cancellation and
capacity increases promote the next waiting person atomically. Re-registering a
cancelled person is an explicit versioned action at the back of the queue.
Cancelling an occurrence closes registration and cancels active bookings. Restoring
it never silently restores registrations or reopens booking. Registration in this
phase is free; paid ticketing requires a separate trusted Payments integration.

`events.read` allows browsing, `events.manage` allows venue and schedule changes,
`events.registrations.read` allows booking lists/history, and
`events.registrations.manage` allows booking management. Migration V15 grants the
administrator these permissions. Other roles can receive them in Accounts & access.

In the serving schedule choose **Event occurrences**, select up to 25 dates, and
reuse the same team and role with arrival/finish buffers. Operational changes,
including a new venue, invalidate linked duty readiness. Review and update the duty
before inviting or confirming people again. A cancelled event never counts as a
ready source. Events and Volunteering communicate through published contracts.

Browse the Events tags at `/docs`. Calendar reads accept an explicit range of at
most 366 days and pages of 1–100 records. Preview endpoints do not reserve dates or
modify records. Create/apply calls require the returned review hash and applicable
optimistic versions. Existing PHP data remains separate.

### Event day, notices and attendance reporting

Open a dated occurrence to use its **Attendance desk**. `events.attendance.read`
permits the searchable roster and history; `events.attendance.manage` permits
arrival recording, walk-ins and corrections. Arrivals are available from two hours
before the scheduled start until the occurrence ends. Only confirmed bookings for
available People records can check in, and the same person cannot have duplicate
attendance for a date. No Children Ministry attendance or pickup authority is
created by general event check-in.

**Register walk-in** uses the occurrence's open registration policy. Existing
waitlisted people retain priority. A full occurrence creates a waitlisted booking
when enabled, and clearly leaves that person unadmitted. Registration settings may
be opened during a gathering, but arrivals and registration cannot reopen after it
ends. Re-registering a cancelled booking requires its current version.

Attendance corrections retain the original arrival, optimistic version, actor and
reason. Correct a mistaken arrival before cancelling that person's booking.
Occurrences with checked-in people cannot be rescheduled onto another date;
occurrence cancellation preserves attendance and displays its count in the review.

Open `/events/reports` for dated attendance totals and CSV export. Registered,
waitlisted, cancelled, attended and absent are separate counts. Absence is derived
only for confirmed bookings after a scheduled occurrence ends. Cancelled dates and
waitlists never create absences. CSV export is limited to 10,000 dated occurrences,
uses the same filters and protects spreadsheet cells against formula injection.

From a booking choose **Prepare email notice**. Choose confirmation, reminder,
cancellation or rescheduling; select a current People email address, review the
rendered wording and record recipient verification/permission evidence. Approval
freezes the published Communications template and content. Dispatch rechecks the
booking, occurrence revision, event, current recipient and live sender permission.
Changed eligibility blocks delivery rather than silently updating or resending it.
Cancellation notices can be prepared for cancelled bookings until 30 days after
the occurrence. Other notices require confirmed upcoming bookings; rescheduling
notices additionally require a changed occurrence revision.

`events.communications.send` permits review, approval and source-scoped delivery
tracking at `/events/email-deliveries`. Starter rich-text templates are managed
under `events.confirmation`, `events.reminder`, `events.cancellation` and
`events.rescheduled` in Communications. They retain the supported variables
`name`, `event`, `startsAt`, `endsAt`, `timeZone`, `venue` and `message`. Approval is
explicit; saving a booking or changing a date never sends an unreviewed message.
Migration V16 grants the administrator these additional permissions.

### Event readiness and closeout

Event managers can open an occurrence's **Readiness & closeout** panel to review
venue availability, free-registration capacity and waitlists, current team coverage,
current-schedule reminder approvals and occurrence-scoped email outcomes. Links
lead to the relevant operational screens according to the caller's permissions.
Readiness is advisory: provisional assignments do not count as ready coverage and
no duties planned requires explicit coordinator review. It does not prevent planning.

`GET /api/events/occurrences/{id}/readiness` is read-only and returns a fresh review
hash. `POST /api/events/occurrences/{id}/closeout` requires `events.manage`, the
current closeout version and review hash, attendance and follow-up acknowledgements,
and a note. Closeout is available at the occurrence end or after cancellation.
Outstanding waitlists, pending/problem deliveries and locally captured mail require
recorded follow-up. SMTP submission and provider-confirmed delivery are reported
separately; approving a reminder never means it was delivered.

Closeout has its own lifecycle and does not change the schedule, cancel waitlists
or send notices. The occurrence lock serializes close/reopen actions with booking,
scheduling and attendance mutations. Concurrent provider outcomes remain external
facts: later changes are surfaced as requiring another review. Immutable history
retains reviewed attendance totals, outstanding work, notes, actor and time.
Attendance corrections retain their existing reason/history requirements and remain
possible after closeout. Changed evidence is flagged; managers can explicitly reopen
with a reason at `POST /api/events/occurrences/{id}/closeout/reopen`. Read paged history
at `GET /api/events/occurrences/{id}/closeout/history`.

Readiness bounds expensive cross-context assessment to 200 duties and 10,000 notices,
fetching delivery summaries in batches of 500. Larger plans are explicitly marked
incomplete; an incomplete notice review cannot be closed out. Staffing is assessed
from current eligibility and is not retrospective staffing evidence. No GET expires
invitations, changes attendance, queues email or creates a closeout row.

### Groups and Ministries

`/api/communities` owns ministry and group definitions, optional parent ministries,
organisational campus/area labels, participation, leadership appointments, meetings,
meeting attendance and ordinary member follow-up. The Java screens are `/groups`,
`/ministries` and `/communities/{id}`. People profiles show paged participation links.
Campus/area is descriptive metadata; it never implies a permission boundary.

Ministry participation, group membership, leadership and volunteer eligibility are
independent. People IDs are reused; no PHP data is imported. Membership and leadership
changes take effect at command time. Ended periods remain in history, rejoining creates
a new period, and PostgreSQL exclusion constraints reject overlapping membership and
appointment periods. Group-to-parent links reference ministries only. Definition and
membership mutations use optimistic versions, reasons where applicable, request IDs,
targeted locks and transaction-scoped auditing rather than a church-wide workflow lock.

Permissions:
- `ministries.read`: browse definitions and, with `people.read`, participation links.
- `ministries.manage`: manage definitions, membership, leadership and group scheduling.
- `ministries.lead`: use the membership, meeting, history and follow-up workspace only
  for an active community with a current appointment linked to the caller's login.
- `ministries.communications.send`: review and approve notices within management or
  current leadership scope. It does not itself confer community access.

Linking a login to an appointment additionally requires `identity.manage` and explicit
identity-verification evidence. Accounts must be active. Ending membership also ends
its leadership appointment. Archive, membership end, appointment end, account disable
and live permission revocation remove subsequent delegated access. Permission checks
run in handlers and again within relevant write locks; directory access does not grant
roster, care or safeguarding access.

Group managers with `events.manage` schedule finite meetings from the group workspace.
The published `CommunityCalendar` contract creates the Events schedule and group links
in one transaction. Venue clashes, timezone/DST validation and finite recurrence limits
remain owned by Events. Group meetings keep chronological source snapshots, updated
transactionally through `OccurrenceScheduleListener`. A published movement policy
prevents moving a date once its group roster has been frozen. Source cancellation
closes new attendance without erasing group evidence, and does not reverse a local
meeting cancellation on source restoration.

Prepare a roster explicitly from two hours before the gathering starts. Preparation
freezes membership eligibility at the meeting's start instant, with a maximum of 5,000
people. Subsequent membership changes do not rewrite that roster. Guests use existing
People identity and never acquire membership automatically. Group attendance is
independent of event bookings and Children's check-ins; it records PRESENT, ABSENT,
EXCUSED or UNRECORDED with versioned, reasoned history. Meetings can be closed after
ending; existing recorded outcomes remain correctable after close/cancellation.
Unrecorded participants never silently become absent. Queries never prepare rosters.

Meeting pages show participation counts and a CSV export of that displayed page,
clearly labelled. Ordinary member follow-up has due dates, completion versions and
history. Sensitive safeguarding/care details belong in their existing dedicated flows.

Communications owns the editable, published `communities.notice` template, rich text
sanitization, delivery queue and provider lifecycle. Variables are `name`, `community`
and `message`. Notice review escapes variables; explicit recipient verification and
an unchanged review hash are required for approval. Approved content is frozen.
Dispatch rechecks the member, contact, community, current sender permissions and
leadership scope. Community delivery tracking is paged and excludes recipient addresses,
verification evidence and message bodies. No membership or scheduling mutation sends
email automatically, and UNKNOWN sends retain the shared manual-reconciliation policy.

### Giving ledger

The Java Giving workspace at `/giving` records funds, manual gifts and full linked
reversals. It starts empty; no PHP financial data is imported. Create a fund before
posting a gift. Amounts use exact integer minor units with two decimal places for
ZAR, USD, EUR and GBP; gifts are bounded to 1,000,000,000,000 minor units. Anonymous
gifts have no donor ID; named donors reference People through its published contract.
People selection additionally needs `people.read` in the screen.

Permissions remain confidential and separate from People and ministry access:
- `giving.read`: view the ledger, donor names, allocations, evidence and period totals.
- `giving.manage`: create/update/archive funds and record manual gifts; implies read.
- `giving.reverse`: record full reversals; management does not imply this permission.

Flyway V19 grants all three to the administrator. Other roles need explicit grants.
Application handlers validate the live caller, permissions and sessions; command actor
IDs must match the caller. All mutations audit the actor in their own transaction.

A gift allocates its full amount to 1–20 distinct active funds. CASH, BANK and OTHER
record money already received; there is no unverified ONLINE posting path. Received
and reversal dates run from 1900 through today in the configured church timezone.
References, if supplied, are unique ignoring case and surrounding whitespace. Keep the
same request UUID when retrying an uncertain response; changing its payload returns
409. Concurrent identical gift retries cannot post duplicate money.

Posted gift headers, allocations, reversals and audit evidence are immutable in
PostgreSQL. Deferred constraints validate allocation balance at commit. Corrections
create one full, separately dated reversal; they never edit/delete the gift or issue a
provider refund. Reversal dates cannot precede receipt. Fund changes use optimistic
versions and reasons; archiving prevents new allocations and preserves history. Gift
posting locks People first, then funds in UUID order. Reversal locks the gift.

`/api/giving/funds`, `/api/giving/gifts`, `/api/giving/gifts/{giftId}`,
`/api/giving/gifts/{giftId}/reversals` and `/api/giving/totals` are documented at `/docs`.
Fund and gift reads use bounded pages (1–100). Date filters are inclusive and limited
to ten years. Ledger filters select received gifts; a fund filter retains the whole
gift amount in that list. Totals use the entire filtered period and only the selected
fund allocation when applicable. Gross gifts use received dates, reversals use their
own dates, and net may be negative. Each currency is reported separately; aggregate
minor units are strings so large totals retain precision in the browser.

This ledger records manual giving. Pledges, statements, acknowledgements and
reconciliation build on it as described below. Verified online gifts and completed
refunds now enter through the Payments contract described below. Giving creates no tax certificates and sends no messages automatically.

#### Pledges, donor statements and financial evidence

Giving now has four staff destinations: **Gift ledger & funds**, **Pledges**,
**Statements & exports**, and **Reconciliation**. The compact mobile selector and
sidebar reach the same screens. The original PHP deployment keeps its existing views;
no PHP financial data is migrated.

A pledge records a donor, fund, target currency/amount and an inclusive commitment
period of at most ten years. Creation requires an available People identity and active
fund. `giving.manage` controls commitments and assignments; People selection also
requires `people.read` in the screen. Current progress is derived, not a manually
asserted fulfilment state. Gifts count only through explicit assignments matching the
donor, fund, currency and received-date period. Assignments cannot exceed the gift's
available fund allocation or the pledge's remaining target. Cancellation retains the
assignment's original creation evidence, cancellation actor/time/reason and optimistic
version. Cancelling a commitment does not silently move its assigned gifts elsewhere;
cancel an assignment explicitly before using that allocation for another commitment.
Reversed gifts cease contributing to current progress. These are current projections,
not retrospective pledge statements.

PostgreSQL composite keys enforce donor/currency/fund linkage. Targeted gift-then-pledge
locks serialize allocation budgets, and database guards prevent over-assignment or
replacement/deletion of assignment evidence. Commitment amounts, dates and identity
are immutable; correct a commitment by cancelling it with a reason and recording its
replacement. Lifecycle and assignment changes use optimistic versions and request IDs.
Read lists and assignment/candidate histories are paged; details include the latest
100 audit entries.

Statements at `/giving/reports` show all allocation lines in an inclusive donor period,
with separate currency totals, original gifts and dated reversals. Reversals of earlier
gifts can produce negative period totals. Generate a statement explicitly, then print
or use the browser's PDF destination. The print surface excludes staff navigation and
form controls. Changing the donor or period clears the displayed statement. Reports
show their generation time and reflect current immutable ledger records and current
People/fund display names; they are not archived statement issuances or tax certificates.
Finance readers without People search can open a named gift and select **View donor
statement**.

Ledger CSV requires both `giving.read` and the new **`giving.export`** permission.
Export uses the entire selected period and optional donor/fund/currency filters, not the
visible ledger page. Signed amounts are integer minor units; text is quoted and guarded
against spreadsheet formulas. Statements and CSV have a strict maximum of 5,000 fund
allocation lines. Larger requests fail with a useful narrowing instruction rather than
silently truncating. Gift/reversal lines come from one database snapshot.

**`giving.acknowledge`** separately authorizes explicit numbered gift acknowledgements.
Open a gift, issue its acknowledgement and reopen or print it from the same place.
There is one per gift, with immutable donor/fund display snapshots and a unique database
sequence number (gaps are possible). Reads and reprints never issue another number.
A gift reversal makes the existing acknowledgement **VOID** while preserving its number,
issue date, allocations and donor/fund wording. New acknowledgements cannot be issued
for reversed gifts. These are acknowledgements only, not tax-deductibility documents.
PostgreSQL validates that acknowledgement allocations match the source gift at issuance.

**`giving.reconcile`** controls bank/cash reconciliation and reviews. Select 1–100
unreconciled, unreversed gifts in one currency; reconciliation cannot precede receipt.
Each gift can belong to only one batch. Compare the recorded total with the amount on
external evidence. A discrepancy requires an explanation and remains flagged for
review. Expected/actual amounts, reference, selected gifts and initial evidence remain
immutable, with database balance/uniqueness checks. Later reversals leave the original
snapshot intact and show the changed current net. Append versioned **FOLLOW_UP** or
**EXPLAINED** reviews with evidence; a subsequent reversal makes the reviewed state
stale and reopens the review flag. Details expose the latest 100 reviews. Neither
reconciliation nor review asserts provider settlement or sends a refund.

Migration V20 grants the three new permissions to the administrator; other roles need
explicit grants. Every protected handler evaluates the live caller/session and relevant
permissions, including after write locks. Documentation for the pledge, statement,
export, acknowledgement and reconciliation endpoints remains on the dedicated API
interfaces and is published at `/docs`.


### Verified online giving

The Payments bounded context and `/payments` workspace now provide durable hosted
checkout requests, verified Payfast/Yoco charges, Payfast monthly giving and confirmed
subscription cancellation, completed partial/full refunds, ledger-posting retries and
versioned bank/fee settlement reviews. Sandbox evidence stays separate from real gifts.
Provider network calls occur outside database transactions. Giving retains ownership of
immutable gifts, dated refunds, acknowledgement voids and pledge corrections.

See [the Java payments guide](../docs/java-payments.md) for the PHP review, staff workflow,
permissions, private configuration and provider pilot requirements. Providers remain
unavailable until configured; no PHP data is migrated. Paid tickets and member self-service
remain separate future work.

### Payment settings

`/administration/payment-settings` lets accounts with the independent `payments.configure` permission
manage provider enablement, test/live environments, protected replacement credentials,
Payfast monthly offers and public callback details. Blank credentials preserve saved values.
The API returns presence flags only and disables caching. Save connection changes disabled,
run a non-charging connection check, then explicitly enable new checkouts. Checks expire for
activation after 24 hours and are invalidated by connection changes. Audit history includes
actor/time/reason/field names, with no secret values or raw provider errors.

New payment orders retain an immutable encrypted configuration revision. Existing checkout,
callback and subscription operations use their original revision after mode switches or
provider disablement. Historical provider keys/webhooks must remain valid externally.
Pre-V22 orders retain deployment credential fallback. The encryption master key stays in
private server configuration. See [the operator guide](../docs/java-payments.md#frontend-provider-configuration).

Payment settings have separate frontend and backend origins. The frontend URL controls
checkout browser returns; the backend URL controls Yoco callback checks and Payfast
notifications. Deployment fallback uses `PAYMENTS_PUBLIC_URL` and `PAYMENTS_BACKEND_URL`
(the latter defaults to the former). V23 preserves URLs on existing immutable revisions.

V24 improves payment status visibility, adds independent `posting-retries`, and manages Yoco
webhooks from Administration with encrypted signing-secret recovery and audited removal.
Posted live gifts support reviewed donor confirmations through Communications' published
`payments.receipt` template and the independent `payments.receipts.send` permission.
Simulations never enter Giving, bank settlement or donor confirmation flows. See
[`docs/java-payments.md`](../docs/java-payments.md) for lifecycle and recovery details.

Donor self-service is available at `/my-giving`. Configure verified account/person
links and explicitly offered online funds under **Administration → Donor access**
before donors begin. Linking requires `identity.manage` and `giving.manage`, with
verification evidence. Donors need a live account session and verified link, without
staff finance permissions. See `../docs/java-payments.md` for the ownership,
revocation, retry and monthly-cancellation rules.

General member access and the Holyspace mobile foundation are documented in
`../docs/member-mobile-api-review.md`. Verify account/person identity under
**Administration → Member access** (`identity.manage` plus `people.manage`).
This does not grant donor, household or guardian access. The new own-profile API
supports permitted name/title/gender and historical contact changes, while device
sessions can be listed and revoked by their owner. Mobile sources and run commands
are in `../mobile/README.md`.

### Member event catalogue and attendance QR

V27 adds opt-in event publication and short-lived single-use member attendance
passes. Staff publish through the event details screen; each occurrence's existing
registration policy still controls availability. The mobile Events tab supports
own booking, FIFO waitlisting, cancellation and attendance QR display. Staff scan
through the existing occurrence's Attendance desk with `events.attendance.manage`.
No existing event is automatically published. See
[`docs/member-mobile-api-review.md`](../docs/member-mobile-api-review.md) for routes,
identity scope, lock ordering and QR validation. Codes do not authorize child
collection or another member's arrival.

### My groups and ministries

Verified members can read their current active community memberships through
`/api/member/communities` and own meeting details through
`/api/member/group-meetings`. These are separate read-only member projections;
staff directory/leadership/roster permissions are unchanged. Current leader names
and roles are visible within an own community, but account bindings, contacts,
other members' attendance and follow-up notes are excluded. Upcoming meetings use
the existing Events schedule through its published batch contract. No new schema
migration or automatic membership grant is needed. See the member mobile API review
for pagination, temporal membership scope and attendance-state rules.

### My volunteering

Member self-service is available at `/api/member/volunteering`, with own invitation
responses and versioned availability replacement beneath that route. Access requires
an explicit volunteer account link and live `volunteering.serve`; general member
identity does not create this permission. Upcoming 90-day duties expose only own
commitment, invitation and readiness information. Acceptance never certifies
readiness, and availability edits never cancel assignments. All own mutations
recheck authority after profile locking and preserve existing source/shift rules.
See the member mobile API review for the contract and security boundaries.

### My children & family ministry

`/api/member/children` exposes guardian-scoped child identity, programme readiness,
upcoming sessions and attendance history. Verified general member identity and
current explicit guardian authority are both required; relationships and household
membership do not confer this access. An authorized guardian with their own pickup
grant can explicitly replace an attendance label with a five-minute collection QR.
The existing staff handover retains identity, authorization, expiry and throttle
checks. Staff arrival-reference lookup remains assigned-session scoped; scanning a
reference does not itself record arrival. V28 adds the guardian index and generated
session-local-date view. See the member mobile API review for routes and lock rules.

### Member inbox

Authenticated member accounts can use `/api/member/notifications` and
`/api/member/notification-preferences`. Communications stores generic own-account
updates, explicit read state and versioned future inbox-topic preferences (V29).
Volunteering source commands and member event booking/cancellation persist notices
inside their transactions with stable deduplication keys. No new permission,
member binding or background job is needed for the inbox itself. Source screens
retain their own authorization. Email preferences and push delivery are separate;
this phase enables neither automatic emails nor device push notifications.

### Member device push

V30 adds encrypted, session-bound push device registrations, opt-in push-topic
preferences and a durable per-notice/device outbox. `/api/member/push` is the own
settings endpoint. Tokens and installation secrets are never returned in listings.
The bounded worker uses live account/session eligibility and performs Expo HTTP
calls outside transactions; it processes provider receipts and retires invalid
registrations. Inbox delivery remains independent.

Live push defaults disabled. Configure `PUSH_ENABLED`, `PUSH_PROJECT_ID`,
`PUSH_TOKEN_KEY`, and optional `PUSH_ACCESS_TOKEN`, then rebuild the mobile native
client with platform credentials. See [activation instructions](../docs/device-push-setup.md).

## Java production operations

Use [the Java production runbook](../docs/java-production-runbook.md) and
[launch tracker](../docs/production-readiness.md). The separate
`compose.java-production.yaml` builds the Java-mode staff workspace, uses
file-mounted secrets and separate migration/runtime roles, and supplies encrypted
backup/restore and isolated release checks. Existing PHP deployment scripts do not
certify the Java application. Production is not activated by these files.
