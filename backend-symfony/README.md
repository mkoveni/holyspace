# OneChurch API (Symfony)

Symfony 8.1 implementation of the OneChurch API, following the architecture of
`../backend-java`. See `AGENTS.md` for conventions.

```
make build && make up      # PostgreSQL (:5434), Mailpit (:8026), app (:8083)
make install               # composer install
make migrate               # apply migrations/V*.sql (shared with the Java app)
make check                 # php-cs-fixer, phpstan, deptrac, phpunit
```

## Contexts

| Context | Purpose |
| --- | --- |
| `IdentityAccess` | Accounts, sessions, permissions, member identity |
| `People` | People directory and member self-service profile |
| `Volunteering` | Serving staffing slice used by Children Ministry |
| `Communications` | Email templates, deliveries and dispatch (SMTP via Symfony Mailer); the member inbox and topic preferences under `/api/member/notifications` and `/api/member/notification-preferences`; device push registrations, topic opt-ins and the push outbox (`app:communications:dispatch-push`, Expo) under `/api/member/push` (off until `PUSH_ENABLED`, `PUSH_PROJECT_ID` and a 32-byte `PUSH_TOKEN_KEY` are set) |
| `Events` | Venues, events and recurring series, occurrence calendar and reviewed schedule changes, registration and waitlist, attendance and reports, readiness and closeout, reviewed notices, member self-service and QR attendance passes under `/api/events` and `/api/member/events` |
| `Giving` | Funds, gifts and full reversals, totals, ledger CSV and donor statements, pledges and assignments, acknowledgements, reconciliation batches, verified donor links and online funds under `/api/giving`; the trusted Payments-facing ledger contracts |
| `ChildrenMinistry` | Modules `Participation`, `Safeguarding`, `Sessions`, `Incidents`, `Messaging`, `Reporting` under `/api/children` and `/api/member/children` |

The Children Ministry, Events and Giving ports are documented in `docs/porting/children-ministry.md`, `docs/porting/events.md` and `docs/porting/giving.md` (status boards, decisions, risks, deviations).

Giving amounts are integer minor units (report totals are strings), and much of its integrity lives in the migrated PostgreSQL triggers (balanced allocations, immutable evidence, one full reversal, assignment capacity). Request hashes follow Java's `GivingHash` (UTF-16 length prefixes), so do not change `Giving\Application\GivingHash` without updating `GivingHashTest`. `OnlineGivingLedger`, `OnlineFundDirectory`, `DonorIdentity`, `DonorIdentityGuard` and `DonorCheckoutGuard` are published for the not-yet-ported Payments context. Every `/api` response is `no-store` with Spring Security's default headers (`Shared\Presentation\SecurityHeadersListener`).

Events publishes `ActivitySource`/`ServingActivityLocks` to Volunteering and `CommunityCalendar`, `CommunityScheduleReader`, `OccurrenceMovementPolicy` and `OccurrenceScheduleListener` to Communities (see `Events\Application\Contract`). Preview hashes (schedule, occurrence change, readiness, notice) are exchanged between a preview and an apply call and are built from Java's record text, so do not reword `Events\Application\JavaText` without updating the golden vectors in `ScheduleHashGoldenTest`.

## Email sending

Sending is off until `COMMUNICATIONS_EMAIL_ENABLED=true` and a sender (`COMMUNICATIONS_EMAIL_FROM`) is configured.
Deliveries are dispatched by `app:communications:dispatch-due`, run every 15 seconds by the scheduler, so production
needs a worker: `php bin/console messenger:consume scheduler_default`. `COMMUNICATIONS_EMAIL_CAPTURE` restricts
the SMTP host to local capture hosts (for example Mailpit).

## Tests

PHPUnit rebuilds the schema of one database per run. Do not run two runs against the same database; for a parallel
run copy `phpunit.dist.xml`, change the database name, and pass it with `-c`.
