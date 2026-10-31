# Java backend conventions

Use clean architecture and Domain-Driven Design (DDD), as described in
`ARCHITECTURE.md`. Organise the modular monolith by bounded context, with
`domain`, `application`, `infrastructure`, and `presentation` packages.

## Java formatting and imports

- Use four spaces for indentation. Never use tabs in Java source files.
- Use Spotless with the checked-in Eclipse profile in `eclipse-formatter.xml`.
  Use four-space continuation indentation and a 120-character line limit. Avoid
  aligning wrapped arguments to the opening parenthesis or adding extra
  indentation for each call in a chain. Let the formatter apply these rules.
- Use explicit imports, one per line. Wildcard imports are forbidden for both
  classes and static members, including assertions and generated jOOQ tables.
- Remove unused imports. Keep static imports separate from regular imports.
- Use explicit local variable types when a method call's return type is not
  obvious at the declaration. Use `var` only when the initializer makes the type
  clear, such as a constructor call. Do not require readers to inspect another
  method's signature to understand the variable's type.
- Use braces for every `if`, `else`, loop, and other control-flow body, including
  single statements. An `else if` may remain a chained conditional.
- Put opening braces on the declaration or control-flow line. Use consistent
  spaces around operators and after commas. Group related fields together and
  separate constructors and methods with one blank line.
- Use UTF-8, LF line endings, a final newline, and no trailing whitespace.
- Format both production and test code. Never edit or format generated files
  under `target`; Maven recreates those files.
- Keep Maven XML consistently indented with four spaces. Follow `.editorconfig`.
- Do not override `equals()`, `hashCode()`, or `toString()` without a concrete
  requirement. Use Java's defaults and record-generated methods where suitable.
  Keep `equals()` and `hashCode()` consistent when domain identity requires custom
  equality. Override record `toString()` only when needed, such as preventing
  passwords, password hashes, or tokens from appearing in logs.

Run `./mvnw spotless:apply` after changing Java code. Spotless checks run during
Maven's `validate` phase and fail the build for formatting or wildcard imports.
Replace wildcard imports explicitly before applying the formatter.

## Functional style

- Prefer `Optional.map`, `flatMap`, `filter`, and `orElseThrow` for optional
  lookups and transformations. Avoid presence checks followed by `get()`.
- Prefer streams for collection transformations and immutable results. Use method
  references when they communicate intent clearly.
- Keep chains short, with explicit types and named intermediate values where
  helpful. Do not wrap ordinary validation in `Optional` just to avoid an `if`.
- Keep domain transformations pure. Keep database writes, locking, auditing, and
  transaction boundaries explicit; use named operations for effectful branches.
- Preserve lazy fallback evaluation and the order of security checks. In login,
  check a dummy password hash for unknown accounts and commit failure tracking
  before throwing an authentication error outside the transaction.

## Architecture and persistence

- Domain owns aggregate roots, value objects, business policies, and aggregate
  repository interfaces. Preserve aggregate identity and enforce invariants in
  domain behaviour. Keep domain independent of frameworks and application code.
- Application handlers coordinate domain behaviour and command transactions.
  Each use case has a typed immutable Command/Query record and one public
  `handle` entry point. External capability interfaces live in
  `application.contract`.
- Each command has a dedicated lowercase package under `application.command`,
  with separate `<UseCase>Command.java` and `<UseCase>Handler.java` files, such as
  `command.createaccount`. Do not nest commands inside handlers.
- Each query follows the same convention under `application.query`, with separate
  `<UseCase>Query.java` and `<UseCase>Handler.java` files, such as
  `query.getaccount`. Do not nest queries inside handlers.
- Queries depend on read-only contracts, never aggregate repositories or write
  interfaces. GET requests must not perform maintenance or writes.
- Infrastructure implements repositories and application contracts using Spring,
  jOOQ, and other integrations. Keep dependency wiring and transaction details
  here.
- Keep persistence stores focused on a cohesive responsibility. Combine related
  reader and writer contracts in the same store, such as `JooqAccessStore`
  implementing `AccessReader` and `AccessWriter`. Account stores may implement
  both the aggregate repository and account reader contracts. Keep application
  interfaces separate so queries depend only on read capabilities. Separate
  accounts, access, sessions, login-attempt tracking, and auditing into distinct
  stores. Reuse pure record mapping where appropriate; keep cross-cutting
  transaction coordination in application use cases and `UnitOfWork`.
- All persistence SQL must use jOOQ's type-safe DSL with generated table, column,
  and record classes. Do not use plain SQL strings or string-based table and
  field lookups. Generated types belong only to infrastructure. Maven generates
  them from Flyway SQL during `generate-sources`.
- Presentation validates transport input and invokes application handlers. It
  must not access persistence directly.
- Bounded contexts integrate through published application contracts. Do not
  share domain aggregates or access another context's persistence.

## Application authorization

- Every protected command and query handler must invoke the framework-independent
  application authorization policy. Other bounded contexts use the published
  `identityaccess.application.contract.PermissionChecker`, implemented by
  `Authorization`, rather than depending on Identity Access implementation classes. Keep controller permission checks as an
  additional HTTP boundary; do not rely on them for direct handler calls.
- Resolve the caller through `CurrentActor`, never through caller-supplied account
  IDs or permission lists. Match command actor IDs to the authenticated caller.
  Validate the current session, account status, and live database permissions.
- Keep Spring Security access in `SpringSecurityCurrentActor`. Application code
  must not access security contexts or use Spring security annotations.
- Read an account's own profile without management permission; require
  `identity.manage` for Identity Access administrative queries and commands. Self-service password
  changes require the caller's own account and the current password. Logout may
  revoke only the caller's current session.
- Perform authorization after the access-change lock for role, permission and
  status changes. Preserve existing transactions and last-manager invariants.
- Public login, session authentication and first-administrator bootstrap are
  explicit entry-point exceptions. Bootstrap must still require an empty account
  store. Background jobs do not gain implicit system or administrator privileges;
  they need a trusted caller adapter and appropriate permissions.
- Test protected handlers directly, including anonymous callers, missing and
  revoked permissions, spoofed actor IDs, and revoked or expired sessions.

## Browser access

- Configure CORS centrally in `shared.infrastructure.web.ApiCorsConfiguration`
  and integrate it with the Spring Security filter chain. Handle preflight before
  authentication; do not permit all `OPTIONS` requests as a substitute for CORS.
- Use `CORS_ALLOWED_ORIGINS` for the exact allowed frontend origins. Bearer-token
  requests do not require cookie credentials. Keep allowed headers, exposed error
  headers, and supported methods explicit. Verify allowed and rejected origins,
  preflight, and authentication/permission error responses.

## API documentation

- Generate OpenAPI 3.1 with springdoc and use Scalar at `/docs` for browsing.
- Put route mappings, parameter bindings and validation, transport records, and
  OpenAPI annotations in dedicated interfaces under `presentation.api` (for
  example, `AuthenticationApi`). Controllers implement these interfaces and
  contain endpoint behavior with `@Override`; do not duplicate interface
  annotations on implementation methods. Keep `@RestController` and runtime
  permission enforcement on controllers. Keep framework annotations out of domain
  and application code.
- Document opaque bearer authentication globally and exempt the public login
  operation. Keep principal injection parameters out of the published schema.
- Keep documentation paths public without relaxing API authentication. Both the
  reference and specification must respect `API_DOCS_ENABLED`.
- Verify the generated JSON/YAML document, public UI, and protected endpoints
  when changing documentation or security configuration.

## Test organisation

Tests must mirror the production package of the class or use case they exercise
under `src/test/java`:

- Command handler tests use the command's package, such as
  `identityaccess.application.command.login`.
- Query handler tests use the query's package, such as
  `identityaccess.application.query.authenticatesession`.
- Domain and value-object tests use their corresponding domain packages.
- HTTP boundary tests use `presentation`.
- Shared test fixtures stay in test-only `shared.infrastructure`.
- Application bootstrap and project-wide architecture checks may remain in the
  application's root package.

Do not collect unrelated context tests in a single root-level class.

Use REST Assured for all new HTTP/API integration tests against the running
Spring Boot server with Testcontainers PostgreSQL. Use per-test request
specifications, never shared mutable RestAssured.baseURI/port/authentication.
Serialize request bodies with the configured JSON mapper; redact credentials and
pickup tokens from test logging. Existing HTTP tests may be converted when touched.

## Verification

Use Java 25 and Maven, with Docker available for PostgreSQL integration tests.
Run the following before completion:

```sh
./mvnw spotless:apply
./mvnw clean verify
```

Verification includes formatting and import checks, ArchUnit dependency rules,
domain invariants, and PostgreSQL-backed HTTP integration tests. Keep schema
changes in versioned Flyway migrations. Preserve the existing API contract unless
the requested change requires otherwise.

## People modelling

- Church membership has exactly four values: Active Member, Regular Visitor,
  Visitor, and Transferred. Visitor is the default. Deceased status and archival
  are separate lifecycle concepts.
- Person titles come from the extensible catalogue; never infer gender, marital
  state, household role, access or ministry eligibility from a title.
- Classify age from date of birth at the requested date. Missing birth dates
  produce UNKNOWN, never ADULT. Use the configured church time zone for today's
  date. Keep the same person ID across birthdays.
- Households group people; they do not establish parenthood, guardianship or
  pickup rights. Allow multiple household memberships with at most one primary
  household. End memberships instead of deleting history. A primary contact
  must reference a current membership in that household.
- Store parent/guardian relationships in their directed form. Canonicalize
  symmetric relationships and derive inverse read views. Serialize graph writes
  using the relationship guard before checking parentage cycles. Acquire person
  locks in UUID string order when locking multiple people.
- Memberships and relationships take effect at command time; backdating and
  scheduling require a separate temporal policy. Their validity periods are
  half-open. Sample command time after acquiring relevant locks.
- Person contact/address changes share the person's optimistic version.
  Household membership/contact changes share the household's version. Stale
  revisions and overlapping associations return 409 and roll back auditing.
- Keep sensitive Children Ministry support data and pickup authorizations out
  of ordinary People profiles. Relationships never confer pickup authority.

## Children Ministry modelling

- Reuse People identity and derive child/adult eligibility from a known birth
  date. Household membership and People relationships never grant guardian,
  consent or pickup authority.
- Keep programme enrolment and dated class placement independent of session
  capacity. Capacity uses room/session seats, qualified present staffing, leader
  presence and the programme's explicit policy.
- Record guardian verification, immutable consent-policy versions, evidenced
  consent and collector grants separately, with validity and revocation history.
- Normalize Instant inputs to PostgreSQL microsecond precision before comparing
  persisted validity periods. Date periods are half-open.
- Acquire child/person locks before session locks; sort multiple person/session
  locks by UUID string. Check capacity and write arrival in one transaction.
  Revalidate destination eligibility for transfers and preserve evidence segments.
- Codes do not prove collector identity or authority. Store pickup code hashes
  only, return codes once, invalidate replaced/transferred labels, and redact
  sensitive request/response textual representations. Commit bad-code tracking
  before raising its error outside the transaction.
- Supervisor exceptions bypass labels only. Preserve collector authorization,
  identity confirmation and audit reasons. Do not trap attending children by
  requiring current enrolment or consent for checkout.
- Sensitive care reads are audited commands with explicit support permission and
  session/management scope. Keep payloads out of ordinary directory/roster views
  and audits. Purge expired payloads through a bounded authorized command.

## Internal modules and Communications

- Children Ministry has `participation`, `sessions`, `safeguarding`, `incidents`,
  `messaging`, `reporting` and `shared` internal modules. Each module retains its
  own Clean Architecture layers. Commands, queries and tests keep their matching
  package structure. See `CONTEXT-MAP.md` for ownership.
- Use module application contracts instead of another module's infrastructure.
  Keep admission and collection invariants within their existing transactions.
- Communications owns email delivery and templates. Children Ministry accesses it
  only through published `communications.application.contract` interfaces and
  retains guardian eligibility and verified-contact rules.
- Store immutable published template revisions and freeze rendered content at
  message approval. Never rerender an approved delivery from a changed template.
- Sanitize rich text on the server, allow variables only as escaped text, reject
  unknown/missing variables, and never execute template expressions. Draft edits
  do not replace the active published revision.

## Volunteer & Team Management

- Keep planning commitment (proposed/confirmed/cancelled) separate from readiness.
  Missing credentials or unknown availability must remain visible without blocking
  provisional planning. Only ready, confirmed staffing counts for Children Ministry.
- Volunteer profiles use People identity and an optional unique account link.
  Linking accounts requires both volunteering management and identity management.
- Credential validity, dated team membership and role approval must cover the full
  duty interval. Availability exceptions override weekly windows; overlapping
  commitments reserve capacity, while adjacent half-open intervals may coexist.
- Lock all affected People records in UUID order before sorted originating activity
  sessions, volunteer rows and shift rows. Use published activity locking contracts;
  never access another context's tables. Recheck source scheduling revisions.
- Shift capacity changes use the aggregate version. Invitations snapshot the
  schedule revision so another booking does not invalidate a pending response.
  Duty edits withdraw invitations. Expiration releases proposed holds only.
- Commands use idempotency identifiers and optimistic versions. GET requests must
  not expire invitations, release holds or send reminders. Maintenance is a bounded,
  explicitly authorized command.
- Use Communications contracts for reviewed, frozen template reminders. Store a
  separate approval hash and recipient verification evidence; recheck contact,
  commitment, source and live approver permissions before dispatch.

## Events modelling

- Keep event metadata, finite recurrence series and dated occurrences separate.
  Preserve occurrence IDs and recurrence origins when dates move. Future-series
  rescheduling preserves individual overrides; cancellation includes them.
- Use explicit reschedule, cancel and restore commands with optimistic versions
  and fresh preview hashes. Bind registration policy revisions to change reviews.
  Restoration must not silently restore bookings or reopen registration.
- Reject ambiguous and nonexistent local times. Bound recurrence generation and
  calendar reads; normalize timestamps to PostgreSQL microsecond precision.
- Keep venue references and occurrence location snapshots. Protect exclusive
  venues against overlapping reservations in PostgreSQL as well as previews.
- Scheduling locks event, series, sorted venues and sorted occurrences. Registration
  locks People identity before its occurrence, and allocates queue positions and
  FIFO promotion atomically. Do not introduce a global registration workflow lock.
- Use published Volunteering activity contracts. Include venue changes in the
  operational revision; never read or update Volunteering tables from Events.
- Keep free registration separate from future paid-ticket processing. Payment
  confirmation requires a trusted Payments contract, never caller assertions.
- Keep event attendance independent of booking state. Check-in requires a confirmed
  booking during the two-hours-before-start to end admission window. Walk-ins use
  open registration, capacity and FIFO waitlists; waitlisting never records arrival.
- Preserve original arrival timestamps and append actor/reason history for versioned
  corrections. Do not move checked-in people onto a different occurrence date or
  erase attendance when an occurrence is cancelled. Derive absence only after a
  scheduled occurrence ends, excluding waitlisted and cancelled bookings.
- Event notices use published Communications templates and explicit recipient
  verification. Freeze reviewed content and recheck booking, schedule, contact and
  live sender access at dispatch. Saving a booking or date never sends notices.
- Event closeout has a separate versioned lifecycle. Lock its occurrence before
  reading versions or recording review evidence. Close only after the scheduled end
  or cancellation, require explicit attendance/follow-up review, and retain immutable
  reviewed totals. Late corrections remain audited and make changed closeouts visible.
  Reopening requires a reason and preserves previous evidence.
- Readiness uses published staffing and delivery contracts. Provisional or unready
  assignments never count as ready staffing. Mark bounded/incomplete summaries
  explicitly; an incomplete notice review must not appear ready for closeout.
  Keep local mail capture, SMTP submission and confirmed delivery distinguishable.

## Groups and Ministries

- Keep ministry/group membership, leadership appointments, volunteer eligibility and
  safeguarding access independent. A group's optional parent must be a ministry.
- Membership and leadership changes take effect immediately with half-open periods.
  Preserve ended periods and create new records on rejoining. Use PostgreSQL exclusion
  constraints and targeted locks, not application-only duplicate checks or a global lock.
- Directory permission exposes definitions, not protected community rosters. Delegated
  reads/writes require a current linked leadership appointment on an active community,
  a valid caller session and live permissions. Login binding also requires identity
  management permission and explicit verification evidence.
- Scheduling uses the published Events contract, atomically creating schedule and group
  links. Source scheduling locks precede community/meeting locks. Attendance locks People,
  then source occurrence, community and meeting. Freeze rosters through an explicit
  command; GET requests never create them. Protect frozen dates from rescheduling.
- Group attendance is separate from bookings and Children's attendance. Preserve frozen
  expected membership and guests, keep UNRECORDED distinct from ABSENT, and require
  versions/reasons for corrections. Cancellation never erases evidence.
- Use Communications templates and frozen, verified approvals. Recheck live membership,
  contact, community and sender/leadership eligibility at dispatch. Ordinary follow-up
  notes must not become an alternative store for sensitive safeguarding/care records.

## Giving

- Keep gifts and their fund allocations immutable. Corrections append one full linked
  reversal with its own date, actor and reason. Reversal does not initiate a refund.
- Use exact integer minor units and explicit supported currencies. Keep aggregate
  totals separate by currency and return large sums as strings. Never round input.
- Balance allocations in the domain and at database commit; lock People before sorted
  funds when posting. Reversals lock their gift. Preserve unique references and request
  hashes so retries cannot duplicate money or silently change intent.
- Finance reads require Giving permission; People and ministry access never imply it.
  Reversal has a separate permission. Never expose donor financial history through
  ordinary People or community read models. Financial dates use the church timezone.
- Pledge assignments must match the donor, fund, currency and commitment dates. Lock
  gift before pledge, recheck available source and target budgets, and retain explicit
  cancellation actor/time/reason. Gift reversals reduce current progress; cancelling
  a commitment does not silently release its historic assignments.
- Issue acknowledgements through an explicit command under the gift lock. Keep one
  immutable numbered snapshot per gift; derive VOID from the source reversal and
  preserve the number on reprints. Do not claim tax deductibility.
- Reconciliation locks selected gifts in UUID order and preserves the original batch.
  Review locks those source gifts before its batch and freezes reviewed reversal totals.
  Later reversals reopen stale review flags. A gift cannot enter a second batch.
- Report complete bounded periods, never silently export the current page. Include
  reversals by reversal date, keep currency totals separate, reject oversized reports,
  and escape spreadsheet text without converting signed amounts into text formulas.


## Payments

- Keep checkout intent, provider verification and Giving posting separate. Persist verified
  charges/refunds before attempting ledger posting; retain failures for an authorized retry.
- Provider HTTP calls must run outside database transactions. Reuse original checkout UUIDs
  and provider idempotency keys after ambiguous responses. Browser returns never post money.
- Webhooks are explicit trusted entry points only after raw-body signature/environment
  verification. Keep notification fingerprints and provider charge/refund references unique.
  Reject conflicting retries; never regress paid state from a late failure notification.
- Sandbox evidence must not touch the real Giving ledger. Use the published OnlineGivingLedger
  capability for live posts/refunds; Payments must not read Giving persistence.
- Lock a payment order before its charges/refunds and Giving effects. Giving refunds lock
  gift then sorted affected pledges. Cancel pledge assignments with explicit refund evidence,
  preserve immutable gift/acknowledgement snapshots and expose remaining assignable credit.
- Separate payment management, completed refund recording and settlement review permissions.
  Recheck live permissions after locks, redact notification bodies/subscription tokens, encrypt
  provider tokens with authenticated encryption and never expose them in read projections.
- Cancellation remains requested until provider confirmation; late charges remain recordable.
  Settlement reviews freeze fee/bank/refund evidence; later refunds invalidate old matches.

## Payment provider settings

- Configuration has its own `payments.configure` permission, independent of finance reads
  and payment management. Commands recheck authorization under the provider-settings lock.
- Keep test/live credentials independent and replace-only. Never return saved credential
  values, include them in audit reasons/fields or echo provider response bodies in errors.
  The master encryption key stays in deployment configuration, outside the settings API.
- Store immutable encrypted revisions and bind new orders before People/fund locks. Existing
  orders, callback verification and subscription management retain their original revision;
  disabling new checkouts or monthly offers must not prevent recording in-flight payments.
- Saving changed connection details must disable new checkouts. Activation requires a fresh
  successful check on that exact revision. Provider probes are explicit commands, use only
  non-charging read endpoints, and run outside transactions. Recheck the settings version
  after the probe so stale results cannot approve newly changed configuration.

- Keep the public frontend origin for browser checkout returns separate from the public
  backend origin for provider callbacks. Pin both to the immutable configuration revision;
  connection checks must compare registered webhooks against the backend origin.

- Keep provider confirmation, Giving posting and settlement status distinguishable. Posting
  retries must use durable verified evidence without charging or requiring bank review data.
  Simulations never require bank settlement or send real donor confirmations.
- Yoco webhook operations retain request identity and encrypted results across uncertainty.
  Never automatically repeat an ambiguous registration or expose its signing secret. Protect
  callbacks referenced by current/historical payments and unresolved removals during activation.
- Donor confirmations use Communications templates and frozen, reviewed recipients/content.
  Require `payments.manage` plus `payments.receipts.send`, a posted unrefunded live contribution
  and current donor contact verification. Recheck live eligibility before dispatch, preserve
  approval/delivery history, and never describe SMTP acceptance or capture as delivery.

## Donor self-service

- Derive donor identity from the trusted live account and an explicitly verified,
  unique Giving account/person link. Never infer ownership from email matches,
  household roles, caller-supplied donor IDs or other ministry account links.
- Verify/revoke/restore financial links with both identity and Giving management,
  optimistic versions and immutable evidence. Preserve identity across revocation;
  do not reassign a verified account or person to bypass historical ownership.
- Keep donor read projections separate from staff finance projections. Scope every
  payment/gift read and command on the server, including checkout and cancellation.
  Recheck identity under command locks; acquire the donor-link lock before People
  and fund locks. GET requests remain read only. Exclude staff notes, bank evidence,
  provider tokens and recipient addresses from donor projections.
- Explicitly opt funds into online giving and enforce the current active offer for
  new requests. Do not block historic verification or subscription management when
  offers, credentials or donor access change. Browser returns never post Giving.

## General member identity and mobile checkout

- Keep general member account/person verification separate from financial,
  household, ministry and guardian capabilities. Require identity plus People
  management, explicit evidence and current versions for member binding changes.
  Preserve canonical person identity across revocation, and serialize binding
  changes on the account root before link and People locks.
- Own People handlers derive the person from verified member identity, never a
  caller-supplied person ID. Profile edits must preserve birth date, membership,
  lifecycle and relationship authority. Contact changes preserve history and use
  the person's version; they do not change the account login email or create
  verified-contact/consent assertions.
- Device-session lists expose only the caller's live sessions and untrusted device
  labels, without tokens/hashes. Self-revocation must not touch other accounts.
- The mobile checkout completion command is an explicit trusted public capability
  entry point: require a hashed one-use expiring handoff bound to a live session,
  account, donor identity and payable order. GET renders only a static page and
  must never consume the capability. POST performs the guarded one-use transition.
  Store checkout payloads encrypted, escape provider forms, allowlist destinations
  and use no-store/no-referrer/CSP. Never put account-session tokens in URLs or
  treat a browser return as payment evidence. Reuse the original payment order.
