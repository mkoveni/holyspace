# Clean architecture and CQRS

This standard applies to every business context: IdentityAccess, People (including
families), LifeEvents, Events, Attendance, ChildrenMinistry, Ministries,
Volunteering, ServicePlanning, Giving, Payments, Communications, Reporting,
Forms, Media and MemberPortal. Shared owns common contracts and technical
readiness/audit queries; business decisions stay in their owning context.

## Dependencies

- Domain contains business models, rules, value objects and repository contracts.
  It does not depend on application, transport or infrastructure code.
- Application contains use cases, immutable messages, view models and ports.
  It depends on domain code and application contracts. It never imports database,
  HTTP, token storage, upload, encryption or container implementations.
- Infrastructure implements application/domain ports using DBAL, Symfony
  security, payment providers, encryption and other external services.
- Presentation translates HTTP input into messages and results into responses.
  It invokes application handlers or dispatches commands through Messenger. It
  never accesses a repository or constructs an infrastructure adapter.

Framework-neutral PHP types are preferred. The existing lightweight Symfony UID
and Clock utilities and Messenger handler discovery attributes are permitted;
HTTP/security/container services belong in adapters. Container wiring, tagged
iterators and aliases belong in configuration or infrastructure. Cross-context
integration uses published application read models/ports and durable integration
messages; do not import another context's persistence implementation or domain
repository.

## Use cases

Each operation has a readonly message implementing `Command` or `Query`, and one
handler with one public `__invoke(Message $message)` entry point. Messages carry
data, not services, callbacks, uploaded files or transport objects. Commands own
state changes and transaction boundaries. Domain rules remain in domain models
or domain policies. A command may return its resulting ID/view for synchronous
HTTP callers; CQRS does not require discarding useful command results.

Queries receive read-only ports and other queries. Repository read stores expose
only read methods; write repositories remain available to command handlers.
Provider configuration, security state, member records, ticket offers and media
reads have separate capabilities from their write operations. Read adapters can
share the same database and repository implementation; CQRS here does not require
separate database servers or duplicate schemas. PostgreSQL JSONB persistence and
field audit triggers stay behind these boundaries.

Do not make GET requests drain the outbox, expire reservations, settle invitations,
repair projections or save defaults. Integration processing is explicit and
queries may observe the previous projection until the worker completes. The
production outbox worker also runs `app:volunteering:settle`, which applies
occurrence changes and expires pending invitations. Payment maintenance continues
through its dedicated worker. Deploy the updated workers alongside the API.

Avoid mixed application services with unrelated public read/write methods and
handlers that merely wrap such services. Put the actual orchestration in the use
case, extract reusable business policies when needed, and keep dependencies
specific to that operation. Batch relationship/projection reads before assembling
lists to avoid queries inside per-row mapping.

## Verification

Run from `backend`:

```sh
composer architecture:check
php vendor/bin/phpunit
php bin/console lint:container
composer cs:check
```

Architecture tests scan every context, including new directories, and reject
adapter dependencies in inner layers, presentation access to repositories,
multi-entry handlers, mutable/untyped messages and write capabilities reachable
from query constructors. Functional tests cover transport behavior, transaction
rollback, optimistic concurrency, integration processing and read purity. Keep
these checks in CI; do not add context exceptions to work around violations.
