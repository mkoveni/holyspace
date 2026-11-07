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
- Email goes through `Communications\Application\Contract\EmailDeliveries`; a context supplies `DeliveryEligibility` and
  `TemplatePublicationPolicy` (tagged services). Communications must not import any other context (`CommunicationsIndependenceTest`).
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
