# OneChurch Symfony backend conventions

Symfony 8.1 / PHP 8.4 port of `../backend-java`. The Java project is the
specification: see its `ARCHITECTURE.md` and `CONTEXT-MAP.md`. Use clean
architecture and DDD as a modular monolith, organised by bounded context.

## Architecture

- Multi-session ports (for example Children Ministry) are tracked in `docs/porting/`: read the plan, take the next
  unchecked session, and update its status board and decision log before finishing.

- Events is a flat context with these documented rules (`docs/porting/events.md`): lock order is person, then occurrence for bookings, attendance and notices; event, series, venues sorted by id, then occurrences sorted by id for schedule changes; occurrence only for closeouts. A reader returns views and a repository returns aggregates, so a class implementing both names its non-locking aggregate load `findUnlocked`.
- Contexts live in `src/<Context>/` with `Domain`, `Application`, `Infrastructure`
  and `Presentation` layers. `Shared` holds cross-context kernel code: `ContextException`/`ErrorKind` (every context's failures extend it), `UnitOfWork`, `AdvisoryLock`, `DbalAuditTable`, the SQL migration runner, the API exception listener and health endpoint.
- A context may be split into modules: `src/<Context>/<Module>/<Layer>` (today only `ChildrenMinistry`; register
  new modular contexts in `tests/Architecture/BoundaryRules::MODULAR_CONTEXTS`). Modules may use a sibling's `Domain` and
  `Application\Contract` namespaces and their context's `Shared` module, never its Infrastructure, Presentation or
  handlers; `ModuleBoundaryTest` enforces it. The `Shared` module depends on no sibling.
- Dependencies point inward (enforced by `deptrac.yaml`): Domain depends on nothing;
  Application depends on Domain; Infrastructure and Presentation depend on both.
- Domain: `final readonly` aggregates, value objects and policies, repository
  interfaces, domain exceptions. No Symfony, DBAL or HTTP types.
- Aggregates have a **private constructor** and named factories: a creation factory (`register`,
  `create`, `add`, `start`) that applies the creation rules, and `reconstitute` for mappers. Never
  `new` an aggregate outside its own class, so no caller can build one in an arbitrary state.
- Aggregates own their timestamps and version. Use `Shared\Domain\Timeline` (created/updated) or
  `Shared\Domain\Revision` (version + Timeline, advanced only by `next($expectedVersion, $now)`,
  which throws `StaleRevisionException`). Handlers pass the instant in (one `$now` per use case,
  shared with the audit row); they never set created/updated dates or bump versions themselves,
  and domain code never reads a clock.
- Commands (state changes) go through `Shared\Application\CommandBus`; queries call their handler
  directly. A command `implements Command` (with `@implements Command<string>` for the id it returns,
  `Command<null>` for none) and its handler `implements CommandHandler`. That marker registers the handler
  on the bus (see `_instanceof` in `services.yaml`); do not add Messenger attributes to Application code.
  The bus wraps every handler in one transaction, so **handlers never inject `UnitOfWork` or open their
  own transaction**, and they must write their audit row inside `handle()` so it commits or rolls back
  with the change. Controllers and console commands inject `CommandBus`, never a command handler.
  The only exception is `Login`, which must commit its failed-attempt counters and then raise.
  Authorization and cheap validation stay at the top of `handle()`.
- Application: one handler per use case. Each lives in its own package, e.g.
  `Application/Command/CreateAccount/{CreateAccountCommand,CreateAccountHandler}.php`
  and `Application/Query/GetAccount/{GetAccountQuery,GetAccountHandler}.php`.
  Commands/Queries are `final readonly` records; handlers expose one `handle()`.
  Read-only contracts live in `Application/Contract`; queries only get readers.
- The read side returns **read models** (`PersonView`, `PersonSummary`, `AccountSummary`, `MembershipView`, ...):
  plain readonly records built by the reader's mapper straight from rows. Readers and query handlers never
  load or return aggregates; only `Domain/Repository` interfaces do, for commands. Read models may use
  domain value objects and enums, not aggregates or entities. A read model never carries secrets (no password hash).
- Contexts integrate only through published `Application/Contract` interfaces,
  never through another context's tables, stores, aggregates, handlers, `Authorization`,
  `Caller` or any other class. `tests/Architecture/ContextBoundaryTest` fails the build on any
  `use App\Other\...` that is not `App\Other\Application\Contract\...` (or `Shared`).
  Authorization: depend on `IdentityAccess\Application\Contract\PermissionChecker`; to learn who
  is calling from a controller, use `AuthenticatedAccount::requireAccount()`.
  When a second context needs a type, publish a contract or move it to `Shared` - do not import it.
- Persistence is **DBAL only** (no ORM, no entity manager). Stores in
  `Infrastructure/Persistence` use the DBAL query builder and explicit mappers.
  Parameterised raw SQL is allowed only for PostgreSQL features the query builder cannot
  express (`ON CONFLICT`, advisory locks); never interpolate values into SQL.
- Schema source of truth is SQL migrations in `migrations/` (copied unchanged from the Java
  Flyway files V1-V30; never edit an applied file, add the next number; V31+ exist only in this project, so keep them backwards compatible with the Java schema). Apply with `make migrate`. Writes run inside a `UnitOfWork` transaction.
- Auth: opaque 256-bit bearer tokens, only the SHA-256 hash stored, session looked
  up on every request, permissions loaded live. Handlers call `Authorization`
  themselves; `#[IsGranted]` on controllers is a second line of defence.
- Stores save with `Revision::previousVersion()` in the WHERE clause and treat 0 changed rows as a conflict.
- Domain entities use string UUIDs and `\DateTimeImmutable`; ids come from `Shared\Application\IdGenerator`,
  time from `Psr\Clock\ClockInterface` (the only vendor type Application may use).
- Controllers in `IdentityAccess` get the caller from its own `Caller`; every other context injects
  `AuthenticatedAccount`. Permissions are checked by handlers through `PermissionChecker`, with
  `#[IsGranted('context.action')]` as the HTTP-boundary check.
- Route placeholders that are UUIDs use `#[PathUuid] string $id` (400 on malformed, lower-cased);
  never call `Json::uuid()` on a path argument. UUIDs in request bodies are validated with `Assert\Uuid`.
- Nested request payloads: `#[MapRequestPayload]` hydrates a nested object property (typed `?SomeRequest`) but not the items of a list, which stay arrays regardless of a phpdoc type. Validate list items with `Assert\All` + `Assert\Collection` and read them as arrays; `Assert\Valid` cannot sit inside `All`.
- Request DTOs declare `expectedVersion` as a required `int` with no default: a missing version must be a
  400, never a silent `0`. The one exception is `IdentityAccess` account updates, where it is `?int`
  because existing clients do not send it: `null` skips the check but still advances the version, and every
  account response exposes `version` so clients can opt in. New contexts must not copy that exception.
- Validation lives in the domain (value objects, `*Rules`). Request DTOs only check shape and length
  so malformed input gets a 400 early; do not invent a rule there that the domain lacks.
- Contains-style search (`ILIKE '%x%'`) needs a trigram GIN index on the exact expression in a new
  migration. Escape the user's text with the context's `LikePattern`.
- Value objects and helpers needed by more than one context live in `Shared` (`CalendarDate`, `Revision`, `Timeline`, `LikePattern`,
  `Timestamps`); move one there the moment a second context needs it instead of importing across contexts.
- Every `/api` response gets Spring Security's default headers from `Shared\Presentation\SecurityHeadersListener` (`no-cache, no-store, max-age=0, must-revalidate` + `Pragma`/`Expires` unless the controller set its own cache headers; `nosniff`, `X-Frame-Options: DENY`, `X-XSS-Protection: 0`, HSTS over HTTPS). A controller that sets `Cache-Control` itself replaces only the cache trio, as in Java.
- A context failure extends `ContextException`; override `retryAfterSeconds()` if its throttle window is not 60 seconds.
- Do not hand-edit `config/bundles.php`; install capabilities with `composer require`.
- Import classes; never write inline fully-qualified names such as `\Doctrine\DBAL\Query\QueryBuilder`.
- Parallel sessions share one test database and each phpunit run rebuilds its schema: never run `make test`/`make check` in two
  sessions at once; if a run fails in `setUp` with foreign-key errors on seed rows, re-run it alone before investigating.
- Restricted data (incident details and history, confidential care text, pickup codes and evidence): readable only through the
  owning module's audited use case (`AccessIncident`, `AccessChildSupport`), which writes its audit row in the same transaction.
  Other modules see counts, dates and names through `Application\Contract` read models only; `RestrictedPayloadBoundaryTest` and
  `ReportingBoundaryTest` fail the build when a type, table or secret column is reached some other way. Never put payloads in
  exception messages, logs or ordinary audit rows.
- Multi-row writes lock in one documented order (child person row, then collector/guardian, then session, then scope; incident row
  before guardian authority) and a handler test asserts it with a recording fake; keep that when adding a write path.
- Giving (`docs/porting/giving.md`) is a flat context holding financial data: amounts are integer minor units, never `float`; integrity triggers stay the source of truth and stores translate their SQLSTATEs (deferred balance and online-source checks are run `IMMEDIATE` inside the store); every `/api/giving` response is `no-store` (the Shared `SecurityHeadersListener`). Lock order: a donor link before People and fund locks; donor person, then funds sorted by id; the gift row before its pledge; gifts sorted by id before a batch. Two-session tests fork a second database session with `tests/Giving/Infrastructure/ParallelSession` and prove it waits on the first session's lock before asserting what it decides.
- Communities (`docs/porting/communities.md`) is flat. Lock order: the person (People `PersonDirectory::lock`), then the community row, which serializes membership and leadership changes; a parent ministry before its group. Meetings: person, then the Events occurrence (`CommunityCalendar::lock`), then the community, then the meeting row (`MeetingLocks`); build Events' `CommunitySchedule` only with `CommunitySchedule::of()`. Notice approval: person, then community; the review is redone under those locks. `#[IsGranted('a|b')]` means any of those permissions. `CommunityHash` values are stored by Java too: keep the Java-captured vectors in `CommunityHashTest` green (regenerate with `docs/porting/vectors/CommunityHashVectors.java`). `CommunityConcurrencyTest` sends the competing request from a second kernel bound to the forked `ParallelSession` connection.
- Volunteering (`docs/porting/volunteering.md`) is flat. Lock order: people sorted by person id, then linked activities (each context locks its own kind through `ServingActivityLocks`), then volunteer rows (`VolunteerLocks`); then team rows sorted by id for shift creation, or the shift, then assignment, then invitation rows for booking changes (`RosterPlanning::lock`). Readiness contracts (`VolunteerReader`, `TeamReader`, `RosterReader`) stay narrow; planning reads use `VolunteerDirectory`/`RosterDirectory`. Duty notices go through `DutyNotices` (`InboxDutyNotices`).
- Payments (`docs/porting/payments.md`) is flat and holds real money and external callers. Provider calls (checkout creation, Payfast validate, subscriptions, connection checks, Yoco webhooks) never run inside a transaction, and verification commits before ledger posting starts in its own transaction (`PaymentPosting`). Use cases that cross that boundary are **provider-boundary handlers**: like `Login` they are not `CommandHandler`s, inject `UnitOfWork` and are called by controllers directly; everything single-transaction goes through the bus. Lock order: the provider settings head (pins the configuration revision), the donor link (`DonorIdentityGuard`), then Giving's donor and fund locks; the order row before Giving posting; a mobile handoff, then its order, then the donor link. `PaymentHash` and the AES-GCM vault (`base64(nonce):base64(ct‖tag)`, `PAYMENTS_TOKEN_KEY`) are shared with Java: keep the Java-captured vectors in `PaymentHashTest`, `AesSubscriptionVaultTest` and `HostedPaymentGatewayTest` green (regenerate with `docs/porting/vectors/PaymentVectors.java`). Only `POST /api/payments/webhooks/{provider}` and `/api/mobile-checkouts/{token}` are public (`security.yaml`). Tests never reach a provider: `ScriptedProviderHttp`, `ScriptedPaymentGateway` and `ScriptedConnectionProbe` replace them in `when@test`.
- Forms (`docs/public-events-and-forms-design.md` section 7) is flat. A published version is immutable and every response pins the version it answered. Anonymity is enforced by V43 constraints: an anonymous response has no respondent and only a date, and a signed-in anonymous respondent leaves an HMAC participation mark with no link to a response. Public and member projections are built by `FormDefinition::projection()` and must never carry `correct` or `points`. Lock order: the form row, serialising the response limit and idempotent retries (`ResponseIntake`). Audience is rechecked in the submit handler by `AudienceGate`; group membership comes from Communities' `CommunityMembershipReader`. Reading who answered an identified form is the audited command `ReadResponses`. `app:forms:purge` (daily, `Schedule.php`) hard-deletes expired responses.
- Email goes through `Communications\Application\Contract\EmailDeliveries`; a context supplies `DeliveryEligibility` and
  `TemplatePublicationPolicy` (tagged services). Communications must not import any other context (`CommunicationsIndependenceTest`).
  Member inbox notices go through `MemberNotifications::publish` inside the originating command transaction; the consuming context adapts it in its own Infrastructure (as `Events\Infrastructure\InboxMemberBookingUpdates` does). A new notice also queues device push jobs; `PushDeliveryProcessor` calls the provider outside any transaction and cancels any job whose device revision, topic opt-in, account or session changed since it was queued.
- Cover images (events, communities): bytes are validated from their content by `Shared\Domain\CoverImage` (JPEG/PNG/WebP, 5 MiB, 8000 px) and stored in `cover_images` under a fresh random id per upload; each context keeps its own versioned pointer table (`event_cover_images`, `community_cover_images`), changes it under its own row lock through `Shared\Application\CoverImage\CoverImages`, and deletes the replaced bytes in the same transaction. Staff use the portal contract `/api/media/{event|community}/{id}` (`X-Image-Version` required); members get `coverImageUrl`, a same-origin `/api/public/cover-images/{id}` path served without a session.
- API tests that need a user with specific permissions use `IdentityApiTestCase::accountWith($username, [...codes])`.
- A temporary stand-in for a module that is not ported yet is named `Pending*`, carries a `PLACEHOLDER` docblock, and is listed as a seam in
  the porting log; a later session replaces it rather than extending it.
- Test handlers in isolation with in-memory fakes (see `tests/People/Application`): permission checked
  first, stale version rejected, audit only on success. One class per file, also in tests, so autoloading works.
- Cover new aggregates with unit tests, and each context with API tests that prove permissions (401/403),
  stale versions (409), rollback of failed commands and malformed input (400).
- Do not violate the SOLID principles when writing code and tests
- Apply the required design patterns where required and, reduce complexity

## Code style

- `declare(strict_types=1)` everywhere, `final` classes, explicit imports, no
  wildcard/grouped use. Braces on every control structure.
- Do not hand-write `__toString`-style helpers or custom equality without need.
- Run `make check` (cs, stan level 8, deptrac, tests) before finishing a change.
- Tooling runs in Docker: `make up`, `make test`, `make stan`, `make deptrac`.
