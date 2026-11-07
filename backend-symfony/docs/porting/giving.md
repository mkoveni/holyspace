# Porting Giving to Symfony - technical approach and session plan

Status: **plan agreed (D1-D5 confirmed 2026-11-07); G0-G9 done (port complete, `make check` green). Open before a mixed Java/Symfony deployment: Java-captured hash vectors (D5).** Update the
[status board](#5-status-board) and the [decision log](#10-decision-log) at the end of every session. This file is the
hand-off between sessions, in the same way as `events.md` and `children-ministry.md`.

Source of truth: `backend-java/src/main/java/org/onechurch/giving` (about 4.1k lines, 13 command handlers, 14 query
handlers, 7 controllers, 39 test files). Read `AGENTS.md` first; every rule there applies. The Java `CONTEXT-MAP.md`
sections "Giving" and the donor/member sections describe intent.

---

## 1. Scope

In scope: the Java `giving` bounded context, 27 endpoints under `/api/giving/**`.

| Capability | Endpoints | Java entry points |
| --- | --- | --- |
| Funds (create, update, list, online-fund flag) | 4 | `GivingController`, `DonorAccessController` (`online-funds`) |
| Gifts: record, list, get, full reversal | 4 | `GivingController` |
| Totals and ledger CSV export | 2 | `GivingReportsController`, `GivingReports`, `GivingCsv` |
| Donor statement | 1 | `GivingReportsController` |
| Pledges, assignments, candidates | 7 | `PledgeController`, `JooqPledgeStore` |
| Acknowledgements | 3 | `AcknowledgementController` |
| Reconciliation batches and reviews | 4 | `ReconciliationController` |
| Donor links (verified financial identity) | 2 | `DonorAccessController` |
| Published contracts | - | `application/contract/*` (11 interfaces) |

Out of scope: the **Payments** context. It is the only Java consumer of Giving (10 files import it) and does not exist in
Symfony. Giving only has to *publish* the trusted contracts Payments needs (`OnlineGivingLedger`, `OnlineFundDirectory`,
`DonorIdentity`, `DonorIdentityGuard`, `DonorCheckoutGuard`). See D3.

No schema work is expected. All Giving tables are already in `migrations/` and are identical to the Java files:

| Migration | Contents |
| --- | --- |
| V19 | `giving_funds`, `giving_gifts`, `giving_allocations`, `giving_reversals`, `giving_history`; permissions; balance constraint triggers (deferred), immutability triggers (`giving_reject_mutation`); unique fund name and gift reference indexes |
| V20 | `giving_pledges`, `giving_pledge_assignments`, `giving_acknowledgements` (+ lines), `giving_reconciliation_batches`, `_items`, `_reviews`; pledge, assignment and balance guard triggers; permissions |
| V21 | `giving_payment_sources`, `giving_payment_refunds`, online-gift guards (`giving_online_source`, `giving_guard_online_reversal`), and the `payments_*` tables (**Payments owns those**) |
| V25 | `giving_donor_links`, `giving_donor_link_history`, `giving_online_funds` |

Much of the integrity is enforced **in PostgreSQL** (balanced allocations, immutable evidence, one full reversal, assignment
guards). The port must keep relying on those triggers and translate their errors, not re-implement them loosely. If a
table does not fit a mapper, the next free migration number is V33 or later (check `ls migrations`).

---

## 2. Source inventory

| Java package | Files | Lines | Notes |
| --- | --- | --- | --- |
| `domain` | 10 (+6 repositories) | ~290 | Records with invariants: `Money`, `Gift` (+`Allocation`, `Reversal`), `Pledge`, `PledgeAssignment`, `Reconciliation` (+`Review`), `DonorLink`, `Fund`, `Acknowledgement`, `GivingRules` |
| `application` (helpers) | 6 | ~280 | `GivingAccess`, `GivingFilters`, `GivingHash`, `GivingReports`, `OnlineGivingService`, `VerifiedDonorIdentity` |
| `application/command` | 13 handlers | ~600 | create/update fund, record gift, reverse gift, pledges and assignments, acknowledgement, reconciliation, donor link, online fund |
| `application/query` | 14 handlers | ~500 | |
| `application/contract` | 11 | ~160 | Readers (internal) and the Payments-facing trusted contracts |
| `infrastructure` | 6 | ~280 (mostly Spring wiring) | Real code: `PublishedDonorCheckoutGuard`, `DonorPersonBindingPolicy` |
| `infrastructure/persistence` | 7 | ~1,170 | jOOQ stores: gifts 297, pledges 280, reconciliation 218 lines |
| `presentation` | 8 + 6 `*Api` | ~540 + ~300 | `*Api` interfaces only carry route annotations |

### Cross-context dependencies (what Giving consumes)

| Contract (Java) | Used for | Symfony today |
| --- | --- | --- |
| `PermissionChecker` | `giving.read/manage/reverse/export/reconcile/acknowledge` | present |
| `PersonDirectory` (15 uses) | donor identity, locks, names | present |
| `MemberIdentity`, `AccountDirectory`, `AccountIdentityGuard`, `AccountNames`, `AuthenticatedAccount` | donor link verification and audit names | present |
| `PersonBindingPolicy` (IdentityAccess) | Giving implements it so a donor-linked person cannot be re-bound elsewhere (`DonorPersonBindingPolicy`) | contract present; Giving adapter to add |

Giving depends on no Events, Volunteering, Children or Communications code.

### Cross-context consumers (what Giving publishes)

Payments only, and only in Java. Nothing in Symfony consumes Giving yet, so these contracts are published and tested
but unused until Payments is ported. `giving_payment_sources` and `giving_payment_refunds` are Giving tables written
through `OnlineGivingLedger`; the `payments_*` tables are not touched by this port.

---

## 3. Target architecture in Symfony

### 3.1 Namespace layout

A single flat context, as for Events. Java has one `giving` package with no internal walls.

```
src/Giving/
  Domain/          Money, Fund, Gift (+Allocation, Reversal), Pledge, PledgeAssignment, Reconciliation (+Review),
                   Acknowledgement, DonorLink, GivingRules, GivingException, Repository/*
  Application/
    Command/<UseCase>/{<UseCase>Command,<UseCase>Handler}.php     (13)
    Query/<UseCase>/{<UseCase>Query,<UseCase>Handler}.php         (14)
    Contract/        readers + the Payments-facing trusted contracts (3.4)
    GivingHash, GivingFilters, GivingAccess, GivingReports, OnlineGivingService, VerifiedDonorIdentity
  Infrastructure/
    Persistence/     Dbal*Store + mappers
    DonorPersonBindingPolicy, PublishedDonorCheckoutGuard
  Presentation/      controllers + request DTOs + CSV writer
```

`GivingException extends ContextException` (`invalid` 400, `missing` 404, `conflict` 409). The Java exception handler also
maps constraint failures; port that as translations inside the stores (3.3).

### 3.2 Money and number handling (the most important design rule here)

- Amounts are **integer minor units** (`long amountMinor`) plus an ISO currency of `ZAR|USD|EUR|GBP`, all with two decimals.
  Use PHP `int` end to end. Never use `float`, and never format with `number_format` on a float. PHP's 64-bit `int` holds
  the Java limits (`Money` max 10^12; reconciliation totals max 10^14; a report sums at most 5,000 lines of 10^12).
  Java uses `BigInteger` for report totals defensively; in PHP use `int` and assert no overflow, or `bcadd` on strings if a
  sum could exceed `PHP_INT_MAX`. Return totals as **strings**, as Java does (`gross`, `reversed`, `net`).
- JSON: amounts leave as the same type Java emits (integers for `amountMinor`, strings for report totals). Diff the
  Java JSON before choosing; do not let `json_encode` turn large ints into floats on 32-bit assumptions.
- Dates are `LocalDate` (`received_on`, `reversed_on`, `starts_on`...). Use `Shared\Domain\CalendarDate`. "Today"
  comes from the clock in the church's calendar zone (see `Shared\Application\ChurchCalendar`), not UTC, because
  `GivingRules::date` rejects any future date. Check what zone Java's `Clock` implies before choosing.
- Aggregates here are mostly **immutable evidence**: gifts, allocations, reversals, acknowledgements, reconciliation
  batches and reviews are insert-only (DB triggers reject updates). Only funds, pledges, assignments and donor links are
  mutable and versioned. Model the insert-only ones as `final readonly` records created through a named factory; do not
  give them `Revision`.

### 3.3 Conventions that matter most for this context

- **Commands** through `CommandBus` (one transaction each, no `UnitOfWork` in handlers); queries call handlers directly.
  Replace every Java `tx.inTransaction(...)`. The balance triggers are `DEFERRABLE INITIALLY DEFERRED`, so they fire at
  commit: make sure the bus transaction commits inside the request so a violation surfaces as a handled 409/400, and write a
  test that an unbalanced insert rolls back.
- **Idempotency**: money-moving commands carry a client request id used as the row id (`Gift::reverse(requestId, ...)`,
  record gift, assignment, acknowledgement, batch). A retry with the same id must return the same result, not a duplicate, and
  the unique indexes (`giving_gift_reference_unique`, `giving_batch_reference_unique`, `giving_online_reference_unique`)
  turn a clash into SQLSTATE `23505`, which maps to 409 "already in use". Port exactly which retries are idempotent and
  which are conflicts; read each handler.
- **Error translation**: `23505` -> 409; the trigger exceptions use `ERRCODE 23514` with constraints `giving_balance` and
  `giving_immutable`; map them to the Java messages. A violated balance trigger is a bug in the handler, so it should
  surface as 500 in tests unless Java maps it.
- **Lock order** (read the Java handler before porting; document it in the slice inventory and assert it with a recording
  fake per multi-lock handler, as AGENTS.md requires). Known from Java comments: `DonorIdentityGuard::lockOwn` is
  acquired **before** People and fund locks; pledge assignment locks the pledge, then the gift allocation row, in a fixed
  order; reconciliation locks its gifts sorted by id.
- **Hash**: `GivingHash::of` is not the same algorithm as Events' `Fingerprint`. It feeds SHA-256 with
  `length + ":" + value` per field (length in **UTF-16 code units**, not bytes), `<null>` for null, `toString()` of other types.
  Use `mb_strlen($v, 'UTF-16LE')`-style counting (or `strlen(mb_convert_encoding($v,'UTF-16LE','UTF-8'))/2`) so non-ASCII
  text hashes identically. Where hashes are exchanged between a preview and an apply, they must match Java's output.
  Add golden vectors captured from Java, including a non-ASCII and a null case.
- **Pagination and limits**: `page()` 1-100, offset 0-100000; ledger reports read at most 5,000 lines; gift allocations 1-20;
  reconciliation 1-100 distinct gifts; pledge period at most 3,660 days; text lengths per field. Copy them from `GivingRules`
  and the domain records.
- **Sensitive data**: financial data is confidential. No giving data on ordinary People profiles; no permission is inferred
  from People or leadership permissions; anonymous gifts have no synthetic person (`donor_id` null). Responses for donor
  access carry `Cache-Control: no-store`. Never log amounts together with names. Add a `GivingIndependenceTest` (no import of
  Events, Volunteering, Children, Communications) and a People boundary test (People code never reads `giving_*`).
- Routes, status codes, JSON field names and error `message` strings match Java exactly.
- Request DTOs declare `expectedVersion` as a required `int` for mutable records (funds, pledges, assignments, donor links).
- CSV export: the ledger CSV (`text/csv`) must keep Java's column order and formula-injection guard (check `GivingCsv`; Events'
  version prefixes `= + - @` with an apostrophe, so confirm Giving does the same before sharing code, and do not share code across
  contexts: put a helper in `Shared` only if both are identical).

### 3.4 Published contracts

Under `App\Giving\Application\Contract`:

| Contract | Kind | Consumer |
| --- | --- | --- |
| `GivingReader`, `GivingLedgerReader`, `PledgeReader`, `AcknowledgementReader`, `ReconciliationReader`, `DonorLinkReader` | internal read ports (queries only get readers) | Giving |
| `DonorIdentity`, `DonorIdentityGuard` | verified financial identity for the current account; guard locks the link before other locks | Giving, Payments |
| `DonorCheckoutGuard` | trusted one-time Payments handoff; caller still verifies its own live session | Payments |
| `OnlineFundDirectory` | funds enabled for online giving | Payments |
| `OnlineGivingLedger` | trusted `validate`, `post(chargeId...)`, `refund(...)`; never exposed over HTTP | Payments |

Implement `PersonBindingPolicy` (IdentityAccess contract) in `Giving\Infrastructure\DonorPersonBindingPolicy` and tag it so
the existing wiring picks it up.

### 3.5 Parity verification

1. Port the 39 Java tests: the `*HandlerSecurityTest` family (permission checked first, stale version, audit only on
   success), domain tests, and the API integration tests; `EventsIntegrationTestSupport`-style helpers become test traits.
2. API tests (`IdentityApiTestCase::accountWith`) per endpoint: success, 401, 403, 404, 409, 400, rollback on failure.
3. Contract diff of JSON against Java and `../postman/OneChurch.postman_collection.json` where it has Giving requests (check;
   the Events collection had none).
4. A database-level test for each trigger the port relies on: unbalanced gift, mutation of posted evidence, second full reversal,
   assignment above the available allocation.

---

## 4. Risks and the behaviours that are easy to get wrong

| Risk | Why | Mitigation |
| --- | --- | --- |
| **Money precision** | A float anywhere corrupts totals | Integer minor units only; PHPStan level 8 plus a test that totals match Java on a fixed dataset |
| **Hash mismatch** | `GivingHash` counts UTF-16 units and uses `<null>` | Golden vectors from Java with non-ASCII and null |
| **Deferred triggers** | Balance is checked at commit, so errors appear after `handle()` returns | Test the full commit path; make the exception listener map `23514` |
| **Full reversal rules** | One full reversal per gift; reversal date not before received date; online gifts reverse only through Payments refund evidence (`giving_guard_online_reversal`) | Port `Gift::reverse` unchanged; test online-gift manual reversal is rejected |
| **Pledge accounting** | assignment must be same donor, currency and period; amount bounded by remaining allocation and remaining pledge target; cancelled pledges accept nothing | Port `Pledge::validateAssignment` unchanged; concurrency test of two parallel assignments on one allocation |
| **Reconciliation** | 1-100 distinct gifts, expected vs actual, discrepancy needs an explanation, reviews are `FOLLOW_UP` or `EXPLAINED`, reversed totals computed | Port `Reconciliation` and review rules; reversed gifts inside a batch must be reported, not hidden |
| **Donor identity** | Financial identity is separate from email matching, households and People relationships; one account cannot be reassigned to another person | Port `DonorLink::revise`; test that household or email matches never grant access |
| **Anonymous gifts** | `donor_id` null must not be given a synthetic person, and statements must still work | Test list, totals and CSV with null donors |
| **Report size** | ledger lines capped at 5,000; over the cap must fail or truncate as Java does | Read `GivingReports`/`GivingFilters`, replicate the exact behaviour |
| **Time zone of "today"** | future-date check uses a clock | Use `ChurchCalendar`; test at the day boundary |
| **Payments absent** | Contracts have no consumer, so mistakes show up late | Write a contract test that exercises `OnlineGivingLedger` and `DonorCheckoutGuard` as Payments would call them, including `post` idempotency on `chargeId` |

---

## 5. Status board

Legend: `[ ]` not started, `[~]` in progress, `[x]` done (`make check` green). Size: S under 1k Java lines, M 1-2k, L 2-3k.

| # | Session | Java source | Size | Needs | Status |
| --- | --- | --- | --- | --- | --- |
| G0 | Foundation: `src/Giving` skeleton wired into deptrac and the container, `GivingException`, `GivingRules`, `Money`, `GivingHash` (+ golden vectors), money/date helpers, `GivingIndependenceTest`, permission codes check | `domain/Money`, `GivingRules`, `GivingException`, `application/GivingHash`, `GivingFilters` | S | - | [x] |
| G1 | Funds and history: `Fund`, create/update/list, audit/history writer (`giving_history`), `GivingAccess` | `Fund`, `JooqGivingStore` (funds), `GivingController` (funds) | S | G0 | [x] |
| G2 | Gifts: `Gift`, allocations, record gift, list, get, full reversal, trigger-error translation | `Gift`, `JooqGivingStore` (gifts), `GivingController` | M | G1 | [x] |
| G3 | Reporting: totals, ledger lines, CSV export, donor statement, filters | `GivingReports`, `GivingFilters`, `JooqGivingLedger`, `GivingCsv`, `GivingReportsController` | M | G2 | [x] |
| G4 | Pledges and assignments: create, cancel, assign gift, cancel assignment, candidates, list/get | `Pledge`, `PledgeAssignment`, `JooqPledgeStore`, `PledgeController` | L | G2 | [x] |
| G5 | Acknowledgements: issue (explicit command, never a posting side effect), get, get by gift | `Acknowledgement`, `JooqAcknowledgementStore`, `AcknowledgementController` | S | G2 | [x] |
| G6 | Reconciliation: create batch, review, list/get | `Reconciliation`, `JooqReconciliationStore`, `ReconciliationController` | M | G2 | [x] |
| G7 | Donor identity and online-fund contracts: `DonorLink`, verify donor link, list donor access, `VerifiedDonorIdentity`, `DonorIdentityGuard`, `DonorPersonBindingPolicy`, set online fund, `OnlineFundDirectory` | `DonorLink`, `JooqDonorAccessStore`, `VerifiedDonorIdentity`, `DonorPersonBindingPolicy`, `DonorAccessController` | M | G1 | [x] |
| G8 | Payments-facing ledger: `OnlineGivingLedger` (validate, post, refund), `DonorCheckoutGuard`, `giving_payment_sources`/`refunds` | `OnlineGivingService`, `JooqOnlineGivingStore`, `PublishedDonorCheckoutGuard` | S | G2, G7 | [x] |
| G9 | Hardening: parity sweep vs Java tests and Postman, trigger tests, concurrency tests, security review, README/AGENTS updates | all | M | G1-G8 | [x] |

Rough total: 10 sessions. G3-G7 are independent of each other after G2 (G7 needs only G1); never two writers on the same files.
G8 is last because it is only exercised by the not-yet-ported Payments context.

---

## 6. Session details

Write the **slice inventory** (handlers, endpoints, tables, cross-context calls, lock order) under each block when you start.

### G0 - Foundation
Deliver: skeleton (`services.yaml` excludes for `src/Giving/Domain`, `Application/Command/*/*Command.php`,
`Application/Query/*/*Query.php`), `Money` with Java's limits and currencies, `GivingRules`, `GivingHash` + vectors, the exception, an
independence test. Prove: limits at both edges, UTF-16 length hashing, `make deptrac` accepts the skeleton.

**G0 slice inventory (done 2026-11-07).**
- Domain: `GivingException` (`invalid`, `conflict`, `missing()` with the fixed "The Giving record was not found.", `forbidden()` -> 403
  "Verified donor access is required."), `Money` (`of`, `currency`, `javaString`), `GivingRules` (`text`, `version`, `date`, `page`),
  `JavaString` (Java `length`/`trim`/`isBlank`).
- Application: `GivingHash`, `GivingFilters`, `Contract\GiftFilter` (Java `GivingReader.Filter`).
- Shared: `CalendarDate::year()` and `CalendarDate::daysUntil()` (signed, `ChronoUnit.DAYS.between`).
- No `services.yaml` or deptrac change was needed: the generic `src/*/Domain/*.php` and Command/Query excludes already cover Giving.
- Tests: `tests/Giving/Domain/{MoneyTest,GivingRulesTest}`, `tests/Giving/Application/{GivingHashTest,GivingFiltersTest,GivingIndependenceTest}`,
  `tests/Giving/Infrastructure/GivingSchemaTest` (6 permissions granted to administrators, 17 tables, the balance/immutability triggers),
  and `CalendarDateTest` additions.

**Next session notes (G1).** Use `GivingRules::text` for fund names (Java trims, so the unique index on `lower(trim(name))` matches), and
`GivingHash::of('fund', actorId, name, description)` for the request hash. Records that feed hashes need a `javaString()` like `Money`'s;
add each one next to its record (allocation lists in G2 render as Java `List.toString`: `[Allocation[fundId=..., amountMinor=...], ...]`).

### G1 - Funds
Deliver: unique fund name (case/trim-insensitive, index `giving_fund_name_unique`), version checks, active flag, history rows
for `FUND`. Prove: duplicate name 409, stale version 409, audit only on success.

**G1 slice inventory (done 2026-11-07).**
- Endpoints (`Presentation/GivingFundsController`): `GET /api/giving/funds?archived&search&limit&offset` -> `{"items":[{id,name,description,active,version}],"total"}`;
  `POST /api/giving/funds` `{requestId,name,description?}` -> 201 `{"id"}`; `PUT /api/giving/funds/{fundId}` `{expectedVersion,name,description?,active,reason}` -> 204.
- Handlers: `CreateFund` (request id is the fund id; hash `GivingHash::of('fund', actorId, name, description)` on the trimmed values; identical retry returns the
  id without a second history row; same id with other details -> 409 "This request identifier was already used for different details."; name clash -> 409
  "A fund already uses this name."), `UpdateFund` (reason via `GivingRules::text(…, 1000, 'Change reason')` before the lock; stale -> 409 from `GivingRules::version`),
  `ListGivingFunds` (`giving.read`, `GivingRules::page`, search trimmed with Java `trim`, blank = no filter, literal case-insensitive contains, order name, id).
- Domain: `Fund` (`create`, `reconstitute`, `revise`), `Repository/FundRepository`, `Repository/GivingHistoryRepository` (writer for `giving_history`, `FUND`/`GIFT`);
  `GivingException::evidenceConflict()` carries Java's `DataIntegrityViolationException` message.
- Application: `GivingAccess` (all six permission checks; writes use `requireActorPermission`, so a spoofed actor id is refused), `Contract\{GivingReader,FundView,FundPage}`.
- Infrastructure: `DbalFundStore` (insert uses `ON CONFLICT DO NOTHING`, covering the primary key and the name index, as Java's `onConflictDoNothing`; a unique
  violation on rename becomes `evidenceConflict`), `DbalGivingHistory`.
- Tables: `giving_funds`, `giving_history`. Migration `V35__giving_fund_search_index.sql` (trigram index on `giving_funds.name`, per the contains-search rule).
- Lock order: one lock only (`giving_funds` row, `FOR UPDATE`) in `UpdateFund`. Cross-context calls: `PermissionChecker` only.
- Tests: `tests/Giving/Domain/FundTest`, `tests/Giving/Application/FundHandlersTest` (+ `InMemoryFunds`, `RecordingHistory`, `RecordingPermissions`, `FakeGivingReader`),
  `tests/Giving/Presentation/GivingFundsApiTest` (retry, 409s, rollback of a failed rename, 400s, 404, 401/403, People permissions grant nothing).
  `GivingIndependenceTest::testNoOtherContextTouchesGivingTables` compared relative paths against `/src/Giving/` and so flagged Giving itself; fixed to `Giving/`.
  The two migration-count tests now expect V1-V35.

**Next session notes (G2).** Reuse `FundRepository::findForUpdate` for allocation funds (lock them sorted by id; Java's `RecordGiftHandler` order must be checked) and
`GivingHistoryRepository::append(…, GIFT, …)`. Extend `GivingReader` with the gift reads rather than adding a second fund reader. `GivingException::evidenceConflict()`
is the message for gift reference / reversal unique violations too.

### G2 - Gifts
Deliver: record gift with 1-20 allocations summing to the amount, donor optional, method `CASH|BANK|OTHER` for manual entry
(`ONLINE` only via G8), external reference uniqueness, list with filters and paging, get with allocations and reversal, one full reversal.
Prove: every `Gift` constructor rule; unbalanced insert rolls back; immutable evidence; reversal before received date rejected.

**G2 slice inventory (done 2026-11-07).**
- Endpoints (`Presentation/GivingGiftsController`): `GET /api/giving/gifts?from&to&currency&fundId&donorId&limit&offset` (`giving.read`) ->
  `{"items":[{id,donorId,donorName,amountMinor,currency,receivedOn,method,externalReference,reversed,reconciled,refundedMinor}],"total"}`;
  `GET /api/giving/gifts/{giftId}` (`giving.read`) -> Java's `Detail` shape `{gift:{id,donorId,money:{amountMinor,currency},receivedOn,method,externalReference,allocations:[{fundId,amountMinor}],createdBy,createdAt},donorName,fundNames:{id:name},reversal:{id,giftId,reversedOn,reason,createdBy,createdAt}|null,history:[{action,reason,actorId,occurredAt}],refundedMinor}`;
  `POST /api/giving/gifts` (`giving.manage`) `{requestId,donorId?,amountMinor,currency,receivedOn,method,externalReference?,allocations:[{fundId,amountMinor}]}` -> 201 `{"id"}`;
  `POST /api/giving/gifts/{giftId}/reversals` (`giving.reverse`) `{requestId,reversedOn,reason}` -> 201 `{"id"}`.
- Handlers: `RecordGift` (manage; method must be `CASH|BANK|OTHER` -> "Online gifts require verified Payments confirmation."; `Money`, then `Gift::record`; received date via
  `GivingRules::date` against `ChurchCalendar::today()`; hash `GivingHash::of('gift', actor, donorId, money, receivedOn, method, reference, allocations sorted by fund id)`; replay check,
  donor lock (unavailable/unknown -> 400 "Choose an available person or record an anonymous gift."), fund locks (missing/inactive -> 409 "An allocated fund is missing or inactive."),
  second replay check, insert, `GIFT_POSTED`). `ReverseGift` (reverse; date, then reason `GivingRules::text(…,1000,'Reversal reason')`, hash `GivingHash::of('reverse', actor, giftId, reversedOn, reason)`;
  lock gift or 404; replay; `ONLINE` -> 409 "Use Online payments to record a provider-completed refund."; existing reversal -> 409 "This gift already has a full reversal."; `Gift::reverse`; `GIFT_REVERSED` with the reason).
  `ListGifts` (read, `GivingFilters::validate`), `GetGift` (read, 404 `missing()`).
- Domain: `Gift` (`record`, `reconstitute`, `allocationsByFund`, `reverse`), `Allocation` (+`javaString`), `Reversal` (`record`, `reconstitute`), `Repository/GiftRepository`.
  `GivingHash` now renders a `list<Allocation>` as Java's `List.toString`.
- Application contract: `GivingReader` gained `gifts(GiftFilter)` and `gift(id)`; read models `GiftSummary`, `GiftPage`, `GiftDetail`, `ReversalView`, `GiftHistoryEntry` (no aggregates).
- Infrastructure: `DbalGiftStore` (any SQLSTATE class 23 on a gift/allocation/reversal write -> `evidenceConflict()`, as Java's `DataIntegrityViolationException` handler);
  `DbalGivingReader` now implements the whole `GivingReader` (`funds()` moved out of `DbalFundStore`, which is only the `FundRepository`). Donor names via `PersonDirectory`
  (`Anonymous` for null donors, `Person unavailable` when the directory has none); `refundedMinor` sums `giving_payment_refunds` (one grouped query per page, Java runs one per row).
- Tables: `giving_gifts`, `giving_allocations`, `giving_reversals`, `giving_history`; read only: `giving_funds`, `giving_reconciliation_items`, `giving_payment_refunds`.
- Lock order: `RecordGift` donor person row, then funds sorted by id (asserted with the shared `LockLog` fake); `ReverseGift` the gift row only, which serialises concurrent reversals.
  Cross-context calls: `PermissionChecker`, `PersonDirectory` (`lock`, `get`, `getAll`).
- Tests: `tests/Giving/Domain/GiftTest`, `tests/Giving/Application/GiftHandlersTest` (+ `InMemoryGifts`, `FakePeople`, `LockLog`; `InMemoryFunds` now logs to `LockLog`),
  `tests/Giving/Presentation/GivingGiftsApiTest`, `tests/Giving/Infrastructure/GiftEvidenceTriggersTest` (unbalanced header rejected at commit, store translates the balance check,
  immutable gifts and allocations, duplicate reference and second reversal are evidence conflicts).

**Next session notes (G3-G6).** Add `totals(GiftFilter)` to `GivingReader` / `DbalGivingReader` (Java `JooqGivingStore::totals` is the source; the controller passes `limit 25, offset 0`).
Use `GiftRepository::findForUpdate` to lock gifts (sorted by id in reconciliation). Concurrency tests (parallel identical posts, parallel reversals) are left to G9: PHPUnit runs one
process, and both paths are serialised by row locks (funds for posts, the gift for reversals) that the handler tests assert.

### G3 - Reporting
Deliver: totals per currency (gross, reversed, net as strings), ledger CSV with the 5,000-line cap, donor statement, filters by
date/fund/donor/method. Prove: reversed gifts counted as negative lines, per-currency grouping, anonymous donors, CSV escaping.

**G3 slice inventory (done 2026-11-07).**
- Endpoints (`Presentation/GivingReportsController`): `GET /api/giving/totals?from&to&currency&fundId&donorId` (`giving.read`) -> `[{currency,grossMinor,reversedMinor,netMinor}]` (decimal strings);
  `GET /api/giving/statements/{donorId}?from&to` (`giving.read`) -> `{donorId,donorName,from,to,generatedAt,lines:[{giftId,kind,date,donorId,donorName,fundId,fundName,currency,amountMinor,method,reference,reason}],totals}`;
  `GET /api/giving/ledger.csv?from&to&currency&fundId&donorId` (`giving.export` and `giving.read`) -> `text/csv;charset=UTF-8`, `Content-Disposition: attachment; filename="giving-ledger-<from>-<to>.csv"`, `Cache-Control: no-store`.
  The controller passes Java's fixed page (25, 0) so `GivingFilters::validate` runs unchanged.
- Handlers (queries, no bus): `GetGivingTotals` (read, then `GivingFilters::validate`, then `GivingReader::totals`), `GetDonorStatement` (read; null donor -> 400 "Choose a donor for the statement."; filter
  donor only), `ExportGivingLedger` (`GivingAccess::export` = read then export). Both reports go through `Application/GivingReports` (validate, `lines(filter, 5000)`, donor name "All donors" /
  directory name / "Person unavailable", `generatedAt` from the clock, totals from the lines: gross = positive lines, reversed = negated negative lines, currencies sorted).
- Contracts: `GivingReader::totals` + `GivingTotal`; `GivingLedgerReader` + `LedgerLine`, `LedgerReport` (read models only).
- Infrastructure: `DbalGivingLedger` (UNION ALL of gift allocations by `received_on`, negated allocations of a reversal by `reversed_on` with its reason, negated `giving_payment_refunds` by `refunded_on`
  with reference and "Provider-completed refund"; ordered date, gift id, kind, fund id; fetches `maximum + 1` and throws 409 "This report exceeds 5000 allocation lines. Narrow the date range or
  choose a donor or fund." instead of truncating); `DbalGivingReader::totals` (same three parts, aggregated in PostgreSQL as `numeric` and returned as text, so totals are exact at any size);
  `LedgerConditions` holds the shared filter (fund filter applies to the allocation). `Presentation/GivingCsv` (BOM, CRLF, every text cell quoted, `= + - @` after Java `stripLeading` gets an apostrophe,
  amount unquoted); `JavaString::stripLeading` added (Character.isWhitespace).
- Tables read: `giving_gifts`, `giving_allocations`, `giving_funds`, `giving_reversals`, `giving_payment_refunds`. No writes, no locks. Cross-context calls: `PermissionChecker`, `PersonDirectory` (`get`, `getAll`).
- Fix to G2: `DbalGiftStore::insert` now sets the balance constraints back to `DEFERRED` after its `IMMEDIATE` check; `IMMEDIATE` lasted for the rest of the transaction, so a second gift in one
  transaction was checked before its allocations existed (no command does this today; G6/G8 tests and Payments would).
- Tests: `tests/Giving/Application/ReportHandlersTest` (+ `FakeLedger`; `FakeGivingReader::totals`), `tests/Giving/Infrastructure/GivingLedgerTest` (ordering, filters, refunds and reversals by their own
  date, anonymous/unavailable donors, the cap at both edges, totals beyond 2^53, report totals equal reader totals), `GiftEvidenceTriggersTest` (two gifts in one transaction),
  `tests/Giving/Presentation/GivingCsvTest`, `tests/Giving/Presentation/GivingReportsApiTest` (Java's totals and statement cases, CSV headers and body, 5,020-line cap -> 409, 400s, 401/403, export needs both permissions).

**Next session notes (G4-G6).** `LedgerConditions` and `DbalGivingLedger` are the place for any further ledger reads; do not add a second ledger query. Refund rows can be seeded directly in tests
(see `GivingLedgerTest::setUp`: online gift, `giving_payment_sources` and the gift in one transaction, then the refund) until G8 ports `OnlineGivingLedger`.

### G4 - Pledges
Deliver: pledge create (period at most 3,660 days, fund and currency), cancel with reason, assign gift allocation to a pledge with
available and remaining-target math, cancel assignment, candidate gifts list. Prove: `validateAssignment` cases, parallel assignments
cannot over-credit, cancelled pledge refuses assignment.

**G4 slice inventory (done 2026-11-07).**
- Endpoints (`Presentation/GivingPledgesController`): `POST /api/giving/pledges` (`giving.manage`) `{requestId,donorId,fundId,amountMinor,currency,startsOn,endsOn}` -> 201 `{"id"}`;
  `POST /api/giving/pledges/{pledgeId}/cancel` (manage) `{expectedVersion,reason}` -> 204; `POST /api/giving/pledges/{pledgeId}/assignments` (manage) `{requestId,giftId,expectedVersion,amountMinor}` -> 201 `{"id"}`;
  `POST /api/giving/pledge-assignments/{assignmentId}/cancel` (manage) `{expectedVersion,reason}` -> 204; `GET /api/giving/pledges?donorId&status&limit&offset` (`giving.read`) -> `{"items":[Summary],"total"}`;
  `GET /api/giving/pledges/{pledgeId}?limit&offset` (read) -> `{summary,assignments:[{assignment:{id,pledgeId,giftId,fundId,donorId,currency,amountMinor,status,version,cancellationReason,cancelledBy,cancelledAt,createdBy,createdAt},receivedOn,giftReversed}],history:[{action,reason,actorId,occurredAt}]}`;
  `GET /api/giving/pledges/{pledgeId}/candidates?limit&offset` (read) -> `[{giftId,receivedOn,availableMinor,reference,method}]`.
  Summary is `{pledge:{id,donorId,fundId,target:{amountMinor,currency},startsOn,endsOn,status,version,cancellationReason,createdBy,createdAt},donorName,fundName,creditedMinor,remainingMinor,fulfilled}`.
- Handlers: `CreatePledge` (manage; `Money::of`, then `Pledge::create` with Java's single message "A pledge needs a donor, fund, currency and a commitment period of at most ten years."; hash
  `GivingHash::of('pledge', actor, donorId, fundId, target, startsOn, endsOn)`; replay, donor lock (unavailable/unknown -> 400 "Choose an available donor."), fund lock (missing/inactive -> 409 "Choose an active fund."),
  second replay, insert, `PLEDGE_CREATED`). `CancelPledge` (lock pledge or 404; version, then open -> 409 "This pledge is cancelled.", then reason; `PLEDGE_CANCELLED` with the reason).
  `AssignPledgeGift` (hash `GivingHash::of('pledge-assignment', actor, pledgeId, giftId, expectedVersion, amountMinor)`; lock gift, lock pledge, **replay before the version check** so a retry survives its own version bump;
  version; reversed gift -> 409 "A reversed gift cannot fulfil a pledge."; available = the gift's allocation to the pledge fund - all refunds of the gift - active assignments of that allocation; `Pledge::assign`
  (open, amount as `Money`, donor/currency/period -> 400 "Choose a gift from this donor, currency and commitment period.", capacity -> 409 "The assignment exceeds the available gift allocation or remaining pledge target.");
  insert, `pledge->revised()`, `GIFT_ASSIGNED`). `CancelPledgeAssignment` (read assignment or 404, lock its gift, lock its pledge, read the assignment again; version, active -> 409 "This pledge assignment has already been cancelled.", reason;
  save it, `pledge->revised()`, `ASSIGNMENT_CANCELLED` with the reason). `ListPledges` (read, page, status `OPEN|CANCELLED` else 400 "Choose open or cancelled pledges."), `GetPledge`, `ListPledgeGiftCandidates` (read, page, 404 for an unknown pledge).
- Domain: `Pledge` (`create`, `reconstitute`, `cancel`, `revised`, `requireOpen`, `assign` = Java `validateAssignment` + the assignment record), `PledgeAssignment` (`record`, `reconstitute`, `cancel`), `Repository/PledgeRepository`.
  `GiftRepository::refunded` added; `GivingHistoryRepository::PLEDGE`.
- Application contract: `PledgeReader` + `PledgeView`, `PledgeSummary`, `PledgePage`, `PledgeAssignmentView`, `PledgeAssignmentEntry`, `PledgeDetail`, `PledgeCandidate`; `GiftHistoryEntry` renamed `HistoryEntry` (Java `GivingReader.History`, used by gift and pledge detail).
- Infrastructure: `DbalPledgeStore` (repository; SQLSTATE class 23 -> `evidenceConflict()`; stale saves -> "The pledge changed. Refresh before trying again." / "This assignment changed."; replay mismatch -> "This request identifier was used for different details.", Java's pledge wording),
  `DbalPledgeReader` (summaries ordered `ends_on, id`; credit = active assignments of unreversed gifts; assignments `created_at DESC, id`; history `occurred_at DESC, id` limit 100; candidates ordered `received_on, id`, empty for a cancelled pledge). Donor names via `PersonDirectory` ("Person unavailable").
- Tables: `giving_pledges`, `giving_pledge_assignments`, `giving_history`; read only: `giving_gifts`, `giving_allocations`, `giving_funds`, `giving_reversals`, `giving_payment_refunds`.
- Lock order: `CreatePledge` donor person row, then fund. `AssignPledgeGift` and `CancelPledgeAssignment` gift row, then pledge row (the order the V20 `giving_assignment_guard` trigger locks in, so the two cannot deadlock). `CancelPledge` the pledge only.
  All asserted with `LockLog`. Cross-context calls: `PermissionChecker`, `PersonDirectory` (`lock`, `getAll`).
- Tests: `tests/Giving/Domain/PledgeTest` (Java's three cases + constructor and period edges), `tests/Giving/Application/PledgeHandlersTest` (+ `InMemoryPledges`, `FakePledgeReader`; `InMemoryGifts::refunds`),
  `tests/Giving/Infrastructure/PledgeTriggersTest` (assignment above the available allocation rejected and translated, ACTIVE->CANCELLED only, no deletes, reversed gift ineligible, immutable versioned commitments),
  `tests/Giving/Presentation/GivingPledgesApiTest` (Java's five pledge API cases, shape, idempotency, rollback of a failed assignment, 400/401/403/404/409, People permissions grant nothing).

**Next session notes (G5-G9).** `HistoryEntry` is the history read model for any Giving detail. The parallel-assignment concurrency case (Java `concurrentAssignmentsCannotExceedTheGiftFundBudget`) is left to G9 with the other
concurrency tests: both requests lock the gift row first, and the guard trigger re-checks capacity, so the second waits and then gets the 409.

### G5 - Acknowledgements
Deliver: issue for a gift (frozen line items, balance trigger), get by id and by gift. Prove: immutability; acknowledging a reversed gift
behaves as Java does (check the handler).

**G5 slice inventory (done 2026-11-07).**
- Endpoints (`Presentation/GivingAcknowledgementsController`): `POST /api/giving/gifts/{giftId}/acknowledgements` (`giving.acknowledge`) `{requestId}` -> 201 `{"id"}`;
  `GET /api/giving/gifts/{giftId}/acknowledgement` (`giving.read`) -> `{"acknowledgement": View|null}` (null also for an unknown gift, as in Java);
  `GET /api/giving/acknowledgements/{acknowledgementId}` (`giving.read`) -> View `{id,giftId,number,donorName,issuedOn,receivedOn,amountMinor,currency,method,reference,voided,reversedOn,reversalReason,lines:[{fundId,fundName,amountMinor}]}`.
  `number` is `G-%08d` of the `number` BIGSERIAL (gaps possible); lines ordered by fund name, then fund id.
- Handlers: `IssueAcknowledgement` (acknowledge; hash `GivingHash::of('acknowledgement', actor, giftId)`; lock gift or 404; **replay before the reversal check**, so a retry still returns the id after a reversal;
  reversal or any refund -> 409 "An acknowledgement cannot be issued for a reversed gift."; existing one -> 409 "This gift already has an acknowledgement. Refresh to open it.";
  donor and fund names from `GivingReader::gift` ("Anonymous" / "Person unavailable"); `issuedOn` = `ChurchCalendar::today()` (Java also uses the church zone here); insert; `GIFT` history `ACKNOWLEDGEMENT_ISSUED` with an empty reason).
  Replay mismatch -> 409 "This request identifier was already used." (Java's acknowledgement wording). `GetAcknowledgement` (read, 404 `missing()`), `GetGiftAcknowledgement` (read, nullable).
- Domain: `Acknowledgement` (`issue` from a `Gift` + fund-name map: one line per allocation in the gift's order; donor name via `GivingRules::text(…, 300, 'Donor name')`), `AcknowledgementLine`, `Repository/AcknowledgementRepository`.
  No `reconstitute`: acknowledgements are never loaded as aggregates.
- Application contract: `AcknowledgementReader` + `AcknowledgementView` (lines reuse the `AcknowledgementLine` value object, as Java's View uses `Acknowledgement.Line`).
- Infrastructure: `DbalAcknowledgementStore` (repository; SQLSTATE class 23 -> `evidenceConflict()`; runs the deferred `giving_ack_balance`/`giving_ack_line_balance` triggers `IMMEDIATE` and restores `DEFERRED`, as `DbalGiftStore`),
  `DbalAcknowledgementReader` (void = a reversal or any provider refund; a refund takes precedence: earliest `refunded_on` and "Provider-completed refund", as Java).
- Tables: `giving_acknowledgements`, `giving_acknowledgement_lines`, `giving_history`; read only: `giving_gifts`, `giving_allocations`, `giving_funds`, `giving_reversals`, `giving_payment_refunds`.
- Lock order: the gift row only (serialises concurrent issues for one gift). Cross-context calls: `PermissionChecker`, `PersonDirectory` (through `GivingReader::gift`).
- Tests: `tests/Giving/Domain/AcknowledgementTest`, `tests/Giving/Application/AcknowledgementHandlersTest` (+ `InMemoryAcknowledgements`, `FakeAcknowledgementReader`; `FakeGivingReader::$details`),
  `tests/Giving/Infrastructure/AcknowledgementEvidenceTest` (mismatched lines and reversed/refunded gifts rejected by the trigger and translated, immutable header and lines, refund precedence, two in one transaction),
  `tests/Giving/Presentation/GivingAcknowledgementsApiTest` (Java's two acknowledgement cases, shape, one per gift, request-id reuse, rollback, 400/401/403/404, `giving.manage` does not grant issuing, People permissions grant nothing).

**Next session notes (G6-G9).** Acknowledgements are not shown in the gift list or detail (Java does not either). Concurrency of two issues for one gift is serialised by the gift row lock
and, failing that, the unique `gift_id`; leave a parallel test to G9 with the others.

### G6 - Reconciliation
Deliver: batch with reference uniqueness, gifts, expected vs actual, explanation when different; reviews with outcome and note; list/get.
Prove: duplicate gift ids rejected, over 100 rejected, balance trigger, reversed amounts reported.

**G6 slice inventory (done 2026-11-07).**
- Endpoints (`Presentation/GivingReconciliationsController`): `POST /api/giving/reconciliations` (`giving.reconcile`) `{requestId,reference,expectedMinor,currency,reconciledOn,reason?,giftIds}` -> 201 `{"id"}`;
  `POST /api/giving/reconciliations/{batchId}/reviews` (reconcile) `{requestId,expectedVersion,outcome,note}` -> 201 `{"id"}`; `GET /api/giving/reconciliations?limit&offset` (`giving.read`) -> `{"items":[Summary],"total"}`;
  `GET /api/giving/reconciliations/{batchId}` (read) -> `{summary,items:[{giftId,donorName,receivedOn,amountMinor,reversed,refundedMinor}],reviews:[Review]}`.
  Summary is `{batch:{id,reference,expectedMinor,actualMinor,currency,reconciledOn,reason,createdBy,createdAt,giftIds},reversedMinor,currentNetMinor,version,review:Review|null,needsReview}`;
  Review is `{id,batchId,version,outcome,note,reversedMinor,createdBy,createdAt}`. Batches ordered `reconciled_on DESC, id`; items `received_on, id` (the summary's `giftIds` follow them); detail reviews `version DESC`, at most 100.
- Handlers: `CreateReconciliation` (reconcile; date via `GivingRules::date` against `ChurchCalendar::today()` (Java also uses the church zone here); reference `GivingRules::text(…,200,'Batch reference')`; gift ids 1-100, no null, distinct, else 400
  "Select 1–100 distinct gifts."; sorted by id; reason Java-trimmed; hash `GivingHash::of('batch', actor, reference, expectedMinor, currency, reconciledOn, reason, sortedIds)`; replay **before** locking, lock gifts sorted (404 when one is missing), replay again;
  wrong currency, received after the date, reversed or already reconciled -> 409 "Every selected gift must be unreconciled, unreversed, in the batch currency and received by the reconciliation date."; actual = sum of the gifts; `Reconciliation::create`
  (reference, `Money::currency`, amounts 1-10^14 and gifts -> "Select 1–100 distinct gifts and a positive expected batch amount.", a mismatch needs "Discrepancy explanation", reason at most 1000); insert; `BATCH` history `BATCH_CREATED` with the reason).
  `ReviewReconciliation` (reconcile; note `GivingRules::text(…,1000,'Review evidence')`; hash `GivingHash::of('batch-review', actor, batchId, expectedVersion, outcome, note)`; find batch or 404; lock its gifts sorted, then the batch; replay **before** the version check;
  version = latest review version (0 before any); `GivingRules::version`; `Reconciliation::review` (version + 1, outcome `FOLLOW_UP|EXPLAINED` else "Choose follow-up or explained.", the current reversed amount); insert; `BATCH_REVIEWED` with the note).
  Replay mismatch for both -> 409 "This request identifier was used for different details.". `ListReconciliations` (read, `GivingRules::page`), `GetReconciliation` (read, 404).
- Domain: `Reconciliation` (`create`, `reconstitute`, `review`), `ReconciliationReview` (`record`), `Repository/ReconciliationRepository`; `GivingHistoryRepository::BATCH`. `GivingHash` now renders a `list<string>` as Java's `List<UUID>.toString`.
  `GivingRules::date` now returns the checked date (callers that ignore it are unchanged).
- Application contract: `ReconciliationReader` + `ReconciliationBatchView`, `ReconciliationReviewView`, `ReconciliationItem`, `ReconciliationSummary` (Java's `needsReview` rule: before a review, a mismatch or any reversal; after one, not `EXPLAINED` or
  written against a different reversed amount; reversed = whole reversed gifts plus refunds of the others), `ReconciliationPage`, `ReconciliationDetail`.
- Infrastructure: `DbalReconciliationStore` (repository; SQLSTATE class 23 -> `evidenceConflict()`; runs the deferred `giving_batch_balance`/`giving_batch_item_balance` triggers `IMMEDIATE` and restores `DEFERRED`), `DbalReconciliationReader` (donor names via `PersonDirectory`, "Anonymous" / "Person unavailable").
- Tables: `giving_reconciliation_batches`, `_items`, `_reviews`, `giving_history`; read only: `giving_gifts`, `giving_reversals`, `giving_payment_refunds`.
- Lock order: `CreateReconciliation` gifts sorted by id; `ReviewReconciliation` gifts sorted by id, then the batch row. Both asserted with `LockLog`. Cross-context calls: `PermissionChecker`, `PersonDirectory` (`getAll`).
- Tests: `tests/Giving/Domain/ReconciliationTest`, `tests/Giving/Application/{ReconciliationHandlersTest,ReconciliationSummaryTest}` (+ `InMemoryReconciliations`, `FakeReconciliationReader`),
  `tests/Giving/Infrastructure/ReconciliationEvidenceTest` (snapshot mismatch, late and reversed gifts refused by the trigger and translated, one batch per gift and per reference, duplicate review version, immutable batches, items and reviews, reader reversal reporting),
  `tests/Giving/Presentation/GivingReconciliationsApiTest` (Java's four reconciliation cases, shape, request-id reuse, rollback, 400/401/403/404/409, other Giving permissions do not grant reconciling, People permissions grant nothing).

**Next session notes (G7-G9).** Java's `concurrentBatchCreationCannotReconcileOneGiftTwice` is left to G9 with the other concurrency tests: both requests lock the gift row first, so the second waits and then sees it reconciled (409); the unique `gift_id` is the backstop.

### G7 - Donor identity
Deliver: `DonorLink` verify/revoke with history, `GET/PUT /api/giving/donor-access`, `PUT /api/giving/online-funds/{fundId}`,
`DonorIdentity`/`DonorIdentityGuard`, `DonorPersonBindingPolicy`, `OnlineFundDirectory`, `Cache-Control: no-store`. Prove: link cannot be
reassigned to another person, household/email matches grant nothing, guard lock precedes People and fund locks.

**G7 slice inventory (done 2026-11-07).**
- Endpoints (`Presentation/GivingDonorAccessController`): `GET /api/giving/donor-access?limit&offset` (`identity.manage` and `giving.manage`, `Cache-Control: no-store`) ->
  `{links:[{accountId,accountName,personId,personName,active,version}],funds:[{id,name,description,active,enabled,version}],hasMore}`;
  `PUT /api/giving/donor-access/{accountId}` (both permissions) `{personId,active,expectedVersion,evidence}` -> 204; `PUT /api/giving/online-funds/{fundId}` (`giving.manage`) `{enabled,expectedVersion}` -> 204.
- Handlers: `VerifyDonorLink` (`identity.manage` + `giving.manage` via `requireActorPermission`; evidence `GivingRules::text(…,1000,'Identity verification evidence or revocation reason')`; `AccountIdentityGuard::lock`; a member identity bound to another person
  -> 409 "The donor must match the verified member identity."; lock the link; when activating, the account must be active and the person (locked) available, else 400 "Choose an active account and available person.";
  no link yet and (version != 0 or not active) -> 409 "Refresh donor access before trying again."; `DonorLink::verify` or `revise` (stale version 409, other person 409 "A verified account cannot be reassigned…"); save with one history row).
  A person already linked to another account hits the unique `person_id` -> `evidenceConflict()` 409.
  `SetOnlineFund` (`GivingAccess::manage`; lock fund or 404; enabling an archived fund -> 400 "Activate this fund before offering it online."; `Fund::revise` with unchanged fields bumps the version (stale 409); `FundRepository::offerOnline`;
  `FUND` history `ONLINE_GIVING_CHANGED` "Offered to donors online." / "Removed from online giving."). `ListDonorAccess` (both permissions via `requirePermission`; limit 1-100, offset 0-100000 else 400 "Choose a valid page.";
  reads limit + 1 for `hasMore`; names via `AccountNames` ("Account unavailable") and `PersonDirectory` ("Person unavailable"); management catalogue over 500 funds -> 409 "The fund catalogue exceeds this view. Contact support.").
- Published contracts: `DonorIdentity` (`current(): ?VerifiedDonor`, `requireOwn(actorId, ?donorId)`), `DonorIdentityGuard` (`lockOwn`), `DonorLinkReader` + `DonorLinkView`, `OnlineFundDirectory` (`funds(management)`, `enabled(fundId)`, `CATALOGUE_LIMIT`) + `OnlineFund`;
  `DonorAccessLink`, `DonorAccessPage` read models. `Application/VerifiedDonorIdentity` implements both identity contracts: only an active link of the live caller to an available person is a donor; a spoofed actor or another donor is 403 `forbidden()`.
- Domain: `DonorLink` (`verify`, `reconstitute`, `revise`), `Repository/DonorLinkRepository` (`lock`, `save`); `FundRepository::offerOnline`.
- Infrastructure: `DbalDonorLinkStore` (upsert on `account_id` + history row, SQLSTATE class 23 -> `evidenceConflict()`), `DbalDonorAccessReader` (implements `DonorLinkReader` and `OnlineFundDirectory`; links `verified_at DESC, account_id`; catalogue `name, id`, at most 501),
  `DbalFundStore::offerOnline` (upsert into `giving_online_funds`), `Infrastructure/DonorPersonBindingPolicy` (auto-tagged `identity.person_binding_policy` through `_instanceof`; vetoes a member link to any person other than the donor link's, active or revoked).
- Tables: `giving_donor_links`, `giving_donor_link_history`, `giving_online_funds`, `giving_funds`, `giving_history`.
- Lock order: `VerifyDonorLink` account root, then donor link, then person (asserted with `LockLog`). `SetOnlineFund` the fund only. `DonorIdentityGuard::lockOwn` takes only the link lock, so callers take People and fund locks after it.
  Cross-context calls: `PermissionChecker`, `AccountIdentityGuard`, `MemberIdentity::boundPerson`, `AccountDirectory::active`, `AccountNames`, `AuthenticatedAccount`, `PersonDirectory` (`lock`, `get`, `getAll`); IdentityAccess consumes `PersonBindingPolicy`.
- Tests: `tests/Giving/Domain/DonorLinkTest` (Java's case + version edge), `tests/Giving/Application/{DonorAccessHandlersTest,VerifiedDonorIdentityTest}` (+ `InMemoryDonorLinks`, `FakeAccounts`, `FakeMembers`, `FixedAccount`, `FakeOnlineFunds`; `InMemoryFunds::offerOnline`),
  `tests/Giving/Infrastructure/DonorAccessStoreTest` (round trip, immutable history, one person per account, catalogue modes), `tests/Giving/Presentation/GivingDonorAccessApiTest` (the Giving-side cases of Java's `DonorGivingApiIntegrationTest` and
  `MemberProfileApiIntegrationTest`: no access from matching email, explicit versioned online opt-in, versioned revocation without reassignment, link uniqueness and both permissions, member/donor identity agreement both ways; plus shape, no-store, rollback, 400/401/403/404).

**Next session notes (G8-G9).** Payments' self-service cases (`/api/my-giving/**`, checkout handoffs) are not portable until Payments exists; `DonorIdentity`, `DonorIdentityGuard` and `OnlineFundDirectory` are the contracts they will call, and G8's contract test should exercise them as Payments would.
`OnlineFundDirectory::enabled` is ready for `OnlineGivingLedger::validate`.

### G8 - Payments-facing ledger
Deliver: `OnlineGivingLedger` over `giving_payment_sources`/`giving_payment_refunds`, idempotent on `chargeId` and `refundId`; manual reversal of
an online gift rejected; `DonorCheckoutGuard`. Prove with a contract test written as Payments would call it.

**G8 slice inventory (done 2026-11-07).**
- No endpoints: the ledger is never exposed over HTTP. Published contracts (Payments only): `OnlineGivingLedger` (`validate`, `post`, `refund`) + `OnlineDonor` (Java `OnlineGivingLedger.Donor`), `DonorCheckoutGuard` (`lockEligible`).
- `Application/OnlineGivingService` implements the ledger. It is not a command handler: Payments calls it from its own handlers, so it injects `UnitOfWork` and each call joins the caller's transaction (or opens one), as Java's `tx.execute`.
  `validate` (lock person: unknown/unavailable -> 400 "Choose an available donor."; lock fund: missing/inactive -> 409 "Choose an active giving fund."). `post` (`GivingRules::date` against `ChurchCalendar::today()` before any lock (Java also uses the church zone here);
  hash `GivingHash::of('verified-payment', charge, donor, fund, amountMinor, reference, receivedOn, actor)`; replay; lock person and fund, both only 404 when missing (Java checks availability and activity in `validate`, not here); replay again; `Gift::online` (ZAR, `ONLINE`, one allocation, the charge id as gift id); insert; `giving_payment_sources` row with the raw reference; `GIFT` history `PAYMENT_POSTED` with the reference).
  `refund` (lock gift or 404; an identical refund id returns **before** the date check, a reused id with other details -> 409 "Refund identity conflicts with existing evidence."; date; `Gift::refundOnline` -> 409 "Refund must match the unrefunded online gift and its dates." (not online, not one allocation, before received, below 1, above the unrefunded amount);
  active assignments of the gift: lock their pledges sorted by id, cancel each with "Source gift refunded; review the remaining credit.", `pledge->revised()`, `PLEDGE` history `PAYMENT_REFUND`; insert the refund; `GIFT` history `PAYMENT_REFUNDED`; every reason is the reference).
- `Infrastructure/PublishedDonorCheckoutGuard`: locks the account's donor link; eligible only when active, for that person, and the person (read, not locked) is available.
- Domain: `Gift::online`, `Gift::refundOnline`, `Gift::ONLINE`, `OnlineRefund` (`record` only; never loaded as an aggregate), `Repository/OnlineGivingRepository` (`source`, `refundExists`, `refund`, `activeAssignments`).
- Infrastructure: `DbalOnlineGivingStore` (SQLSTATE classes 22 and 23 -> `evidenceConflict()`; `source` runs the deferred `giving_online_source` trigger `IMMEDIATE` and restores `DEFERRED`, as `DbalGiftStore`); `PledgeAssignmentRow` (mapper now shared with `DbalPledgeStore`).
  `config/services.yaml` `when@test` aliases make the two contracts public for the contract test (nothing in `src` consumes them until Payments exists).
- Tables: `giving_gifts`, `giving_allocations`, `giving_payment_sources`, `giving_payment_refunds`, `giving_pledge_assignments`, `giving_pledges`, `giving_history`; read only: `giving_funds`, `giving_donor_links`.
- Lock order: `validate`/`post` person row, then fund; `refund` gift row, then pledges sorted by id (the order of every assignment write and the assignment guard trigger); `DonorCheckoutGuard` the donor link only, so it precedes People and fund locks. All asserted with `LockLog`.
  Cross-context calls: `PersonDirectory` (`lock`, `get`). Manual reversal of an online gift was already refused by `ReverseGift` (G2) and the `giving_online_reversal_guard` trigger; both are now proven with a real posted online gift.
- Tests: `tests/Giving/Domain/GiftTest` (online factory, refund bounds), `tests/Giving/Application/{OnlineGivingServiceTest,PublishedDonorCheckoutGuardTest}` (+ `InMemoryOnlineGiving`, `InlineUnitOfWork`),
  `tests/Giving/Infrastructure/OnlineGivingStoreTest` (online gift without source rejected at commit, unique charge and reference, two online gifts in one transaction, refund identity and immutability, the refund guard trigger translated (over the gift, reused reference, reference over 200), no refund of a manual gift, no manual reversal of an online gift, assignment order),
  `tests/Giving/Presentation/OnlineGivingContractTest` (the Giving outcomes of Java's `PaymentsApiIntegrationTest`: one gift per verified charge and no manual reversal, dated idempotent partial refunds that cannot exceed the gift, a refund voids the acknowledgement, releases pledge credit and appears on the statement, case-sensitive references; plus rollback with the caller's transaction and the checkout guard over real donor links).

**Next session notes (G9).** Java's `concurrentCallbacksDeduplicateByProviderReference` maps to two parallel `post` calls for one charge: both lock the donor, then the fund, and the second sees the replay; the primary key and `giving_online_reference_unique` are the backstop. Leave it with the other concurrency tests.
The G7 note asked the G8 contract test to cover `DonorIdentity`, `DonorIdentityGuard` and `OnlineFundDirectory` too; only `DonorCheckoutGuard` and the ledger are covered there, because the identity contracts read the live caller (`AuthenticatedAccount`) and need a request context. They keep their G7 unit tests (`VerifiedDonorIdentityTest`) and the donor-access API tests; G9 should add an authenticated contract test for them.
When Payments is ported, its handlers call the ledger inside their bus transaction; note that DBAL nests with savepoints, so a ledger failure caught *inside* the caller's transaction only rolls back the ledger's writes (Java marks the whole transaction rollback-only). Java's `PaymentPosting` catches outside its transaction, so the outcome is the same there.

### G9 - Hardening
Deliver: parity and trigger test suite, concurrency tests (parallel assignments, parallel reversals of one gift, duplicate references),
security review (no financial data on People surfaces or in logs), README/AGENTS updates.

**G9 slice inventory (done 2026-11-07).**
- Route parity: the 27 Java routes and the 27 Symfony `/api/giving` routes match by method and path (`debug:router`). The Postman collection has no Giving requests.
- Java test mapping: every `GivingApiIntegrationTest`, `GivingReportingApiIntegrationTest`, `JooqGivingLedgerIntegrationTest`, `GivingCsvTest` and domain test case has a Symfony counterpart (same or split name); the 27 `*HandlerSecurityTest` classes map to the per-area handler tests (permission first, spoofed actor, stale version, audit only on success).
  Not ported: the two OpenAPI checks (`/v3/api-docs` in `pagingFiltersAndPublicDocumentationAreBounded` and `GivingSchemaConfigurationIntegrationTest`), because Symfony publishes no OpenAPI document (as in Children Ministry); the paging half of that test is covered. Payments' own tests wait for Payments.
- Trigger tests (3.5): unbalanced gift (`GiftEvidenceTriggersTest`), mutation of posted evidence (same, plus the pledge, acknowledgement, reconciliation and refund evidence tests), second full reversal, and assignment above the available allocation (`PledgeTriggersTest`) were already in place; nothing missing.
- Concurrency (`tests/Giving/Infrastructure/GivingConcurrencyTest`, helpers `ParallelSession` and `GivingSession`): the first session runs the real handler with its transaction open; a forked second session (own connection) runs the competing one and must show `wait_event_type = 'Lock'` in `pg_stat_activity` before the first commits; then the second session's decision is asserted.
  Cases: identical gift posts (the second replays the same id; one gift, one `GIFT_POSTED`), two gifts with one external reference (409 evidence conflict), two reversals of one gift (409 "already has a full reversal"), two assignments on one allocation (409 capacity), two batches on one gift (409), two acknowledgements of one gift (409), two online posts for one charge (replay; one gift, one source).
  Mutation check: removing `RecordGift`'s post-lock replay makes the identical-post case fail with a 409 where Java returns the same gift, so the tests exercise the re-check, not only the lock.
- Authenticated contracts (`tests/Giving/Presentation/DonorIdentityContractTest`): `DonorIdentity`, `DonorIdentityGuard` and `OnlineFundDirectory` over real accounts, sessions and donor links, with the live caller set in the token storage as the firewall does. Unauthenticated is 401; only an active verified link gives an identity (permissions do not); `requireOwn`/`lockOwn` refuse another donor and a spoofed actor; revocation ends the identity; only active, explicitly offered funds are online. `config/services.yaml` `when@test` makes the three contracts public.
- Security review: no code outside `src/Giving` mentions `giving_*` or imports `App\Giving` (`GivingIndependenceTest`, `ContextBoundaryTest`); Giving has no logger calls; DBAL 4 exception messages carry no bound parameters, the trigger messages carry no values, and every unique/trigger violation is translated before `ApiExceptionListener` would log it; exception messages name no donor, amount or record (`missing()` is deliberately generic).
  **Cache headers (fixed):** Java's `SecurityConfiguration` keeps Spring Security's default headers, so every Java response carries `Cache-Control: no-cache, no-store, max-age=0, must-revalidate`, `Pragma: no-cache`, `Expires: 0` unless the controller sets its own. Symfony's default `no-cache, private` allows storage. The new `Presentation/GivingNoStoreListener` (since replaced by the Shared `SecurityHeadersListener`, see the decision log) applied Spring's headers to every `/api/giving` response (errors, 401 and 403 included) that is not already `no-store`; `GivingNoStoreApiTest` proves it and that other contexts are untouched.
  **`int` DTO coercion (G2 note):** probed with People's `RenameHouseholdRequest`: `0.9`, `"0"` and `true` are 400 and only an integral `0.0` is accepted, so other contexts do not truncate fractions (Java's Jackson does, by default). No change.

**Next steps after the port.** (1) Capture Java hash vectors (D5, G0 log). (2) The missing Spring default headers affect every Symfony context, not only Giving; a `Shared` response listener would restore Java parity everywhere (Giving's listener can then go). (3) Payments: its handlers call `OnlineGivingLedger` inside their bus transaction (see the G8 notes on savepoints).

---

## 7. Definition of done for each session

1. `make check` (cs, stan level 8, deptrac, phpunit) is green.
2. Every ported handler has an isolated test: permission checked first, stale version rejected where versioned, audit only on success.
3. Every new endpoint has an API test covering 401, 403, 404, 409, 400 and rollback.
4. Board ticked, decisions and deviations logged, "Next session notes" written for anything half-done.
5. No commit unless asked.

---

## 8. Kickoff prompt for a new session

> Continue the Giving port. Read `backend-symfony/AGENTS.md` and `backend-symfony/docs/porting/giving.md`, then do the
> next unchecked session on the status board. Java is the specification. Finish with `make check` green and update
> the board and decision log.

---

## 9. Open decisions (confirm before G0)

| # | Question | Recommendation |
| --- | --- | --- |
| D1 | Money representation | Integer minor units + currency string everywhere; totals as strings (3.2). No decimal library |
| D2 | Flat context or modules? | Flat (3.1). One cohesive Java package |
| D3 | Payments is not in Symfony. Port the Payments-facing contracts now? | Yes, last (G8), tested by a contract test; do not start Payments itself in this plan |
| D4 | Where to implement integrity: PHP or database? | Keep the migrated triggers as the source of truth and add PHP-side checks that produce Java's messages first; never weaken or edit applied migrations |
| D5 | Cut-over while both backends share one database | Route Giving traffic per environment, not per endpoint, because request ids, references and hashes assume one writer implementation. Capture Java hash vectors first (see G0) |

---

## 10. Decision log

| Date | Decision | Notes |
| --- | --- | --- |
| 2026-11-07 | D1-D5 confirmed as recommended | D1: integer minor units, totals as strings. D2: flat `Giving` context. D3: Payments-facing contracts last (G8), Payments itself not in this plan. D4: migrated triggers stay the source of truth. D5: route Giving traffic per environment, not per endpoint; capture Java hash vectors first. |
| 2026-11-07 | G0 deviations | `Money` uses a private constructor with `Money::of` (AGENTS.md) instead of Java's public record constructor, and `Money::currency` replaces Java's `new Money(1, currency)` currency-only check. Text limits, trimming and blank checks follow `java.lang.String` exactly (`JavaString`): length in UTF-16 units, `trim` strips only U+0000-U+0020, `isBlank` excludes no-break spaces; Events uses `mb_strlen` and Unicode trimming, so the two contexts differ on purpose for non-BMP and Unicode-space input. `GivingHash` keeps Java's collision between `null` and the literal text `<null>` |
| 2026-11-07 | G0 hash parity (open) | `GivingHashTest` vectors were computed by an independent Python implementation of Java's algorithm (no JVM available) and are marked PROVISIONAL. Before any mixed Java/Symfony deployment (D5), capture at least a fund, a gift (with `Money` and a date) and a non-ASCII request hash from Java and add them as vectors |
| 2026-11-07 | G0 check status | `make cs`, `make deptrac` and the full phpunit suite (1193 tests) pass. `make stan` reports 3 errors, all in `src/Communities` (in progress in another session: `ErrorKind::INVALID`/`CONFLICT` constants and one missing array value type); Giving has none |
| 2026-11-07 | G1 deviations | `GET /api/giving/funds` uses `#[IsGranted('giving.read')]` instead of Java's `hasAnyAuthority('giving.read','giving.manage')`: `giving.manage` implies `giving.read` (`IdentityRules::effectivePermissions`), so the outcome is the same. Java's second `access.manage` inside the transaction is dropped because the bus already runs the whole handler in one transaction. `archived` that is not a boolean is a 400 (Java's binder also rejects it). Added V35 (trigram index on fund names) for the contains search, as AGENTS.md requires; it is backwards compatible with the Java schema. |
| 2026-11-07 | G1 check status | cs, deptrac and the full phpunit suite (1224 tests) pass. `make stan` still reports the same 3 errors in `src/Communities` (other session); Giving has none |
| 2026-11-07 | G2 deviations | "Today" for received and reversal dates is `ChurchCalendar::today()` (church zone). Java uses `LocalDate.now(Clock.systemUTC())`, so between midnight and 02:00 in Johannesburg Java rejects the church's own date as future; its API docs promise the church time zone, so Symfony follows the docs. The deferred balance triggers are forced to run inside the store (`SET CONSTRAINTS giving_header_balance, giving_allocation_balance IMMEDIATE`) so a violation becomes the same 409 evidence conflict Java returns at commit, instead of the shared listener's generic 400 after commit. Java's second `access.manage` inside the transaction is dropped (as in G1). `RecordGiftRequest::$amountMinor` is `mixed` + `Assert\Type('int')`: the serializer would otherwise truncate `100.9` to `100` (other contexts' `int` DTO fields have the same coercion and should be checked in G9). |
| 2026-11-07 | G2 check status | cs, deptrac and the full phpunit suite (1269 tests) pass. `make stan` still reports the same 3 errors in `src/Communities` (other session); Giving has none |
| 2026-11-07 | G3 deviations | `GET /api/giving/totals` lives in `GivingReportsController` with the other reports (Java: `GivingController`); the route, query and JSON are identical. `#[IsGranted('giving.read')]` replaces `hasAnyAuthority('giving.read','giving.manage')` (as G1); the CSV has two `IsGranted` attributes for Java's `giving.export and (read or manage)`. Totals are aggregated in one PostgreSQL query over the three parts and cast from `numeric` to text, where Java sums three grouped `BigDecimal` results in Java: same numbers, no PHP big-integer library needed (bcmath is not installed). Report totals use PHP `int`, bounded by 5,000 lines of at most 10^12 (Java uses `BigInteger`). The statement JSON keeps Java's default caching (no `no-store`); only the CSV sets it, as Java does. A statement for an unknown person is 200 with "Person unavailable" and no lines, as in Java. `GivingCsv` is not shared with Events' CSV: Giving quotes every text cell, writes a BOM and uses Java `stripLeading`. `DbalGiftStore` now restores `DEFERRED` after its `IMMEDIATE` balance check (G2 bug, see the G3 inventory). |
| 2026-11-07 | G3 check status | cs, deptrac and the full phpunit suite (1292 tests) pass. `make stan` still reports the same 3 errors in `src/Communities` (other session's untracked work); Giving has none. `make check` therefore stops at stan; the remaining targets were run individually |
| 2026-11-07 | G4 deviations | Pledge reads use `#[IsGranted('giving.read')]` for Java's `hasAnyAuthority('giving.read','giving.manage')` (as G1). Request DTOs require `expectedVersion` (Java's primitive `long` defaults a missing one to 0; AGENTS.md wants a 400). `amountMinor` is `mixed` + `Assert\Type('int')` (as G2), so `1.9` is a 400 as in Java. Java's second `access.manage` inside the transaction is dropped (as G1/G2). Java's `JooqPledgeStore` is both repository and reader; Symfony splits it into `DbalPledgeStore` and `DbalPledgeReader`, and the reader returns `null` for an unknown pledge's candidates so the handler raises the 404 (Java's reader throws). `Pledge::assign` runs Java's `validateAssignment` and builds the `PledgeAssignment`, so an assignment can only come from a validated pledge. Pledges and assignments keep Java's plain `version` (no `updated_at` column, so `Shared\Domain\Revision` does not fit), like `Fund`. `PledgeRepository::findAssignment` is `@phpstan-impure` because the cancel handler reads it again under the locks. |
| 2026-11-07 | G4 check status | cs, deptrac and the full phpunit suite (1330 tests) pass. `make stan` still reports only the same 3 errors in `src/Communities` (other session's untracked work); Giving has none. `make check` therefore stops at stan; the remaining targets were run individually |
| 2026-11-07 | G5 deviations | Reads use `#[IsGranted('giving.read')]` for Java's `hasAnyAuthority('giving.read','giving.manage')` (as G1). Java's second `access.acknowledge` inside the transaction is dropped (as G1/G2). Java's `JooqAcknowledgementStore` is both repository and reader; Symfony splits it into `DbalAcknowledgementStore` and `DbalAcknowledgementReader` (as G4). `Acknowledgement::issue` builds the lines from the `Gift` itself, so the snapshot cannot drift from the allocations; an allocation without a fund name is `missing()` (Java would insert a null `fund_name` and fail on the NOT NULL column with an evidence conflict; unreachable, since every allocation references an existing fund). The handler returns the nullable view and the controller wraps it in `{"acknowledgement": ...}` instead of a `Status` record; the JSON is identical. Java's refund date is a separate query per view; Symfony uses a correlated subquery in the same select. No other behaviour changed. |
| 2026-11-07 | G5 check status | cs, deptrac and the full phpunit suite (1357 tests) pass. `make stan` still reports only the same 3 errors in `src/Communities` (other session's untracked work, unchanged since 10:16); Giving has none. `make check` therefore stops at stan; the remaining targets were run individually |
| 2026-11-07 | G6 deviations | Reads use `#[IsGranted('giving.read')]` for Java's `hasAnyAuthority('giving.read','giving.manage')` (as G1). `ReviewBody.expectedVersion` is a required `int` (Java's primitive `long` defaults a missing one to 0; AGENTS.md wants a 400). `expectedMinor` is `mixed` + `Assert\Type('int')` + `Range(1, 10^14)` for Java's `BigDecimal` with `@Digits(fraction = 0)`, so `100.5` and `"10000"` are 400s. Java's second `access.reconcile` inside the transaction is dropped (as G1/G2). Java's `JooqReconciliationStore` is repository and reader; Symfony splits it into `DbalReconciliationStore` and `DbalReconciliationReader` (as G4), and the `needsReview` rule moves from the store to `ReconciliationSummary::of` so it can be unit tested. Java rebuilds the batch through its validating constructor on every read; Symfony's reader builds read models directly, and only `find` (for reviews) goes through `Reconciliation::reconstitute`. Kept from Java on purpose: an unsupported currency such as `JPY` is a 409 (each gift is checked against the batch currency before the currency rule runs), and an invalid outcome is checked after the version, so a stale review with a bad outcome is a 409. Gift ids are lower-cased in the controller, so the same id in two cases is a duplicate (400). |
| 2026-11-07 | G6 check status | cs, deptrac and the full phpunit suite (1389 tests) pass. `make stan` still reports only the same 3 errors in `src/Communities` (other session's untracked work); Giving has none. `make check` therefore stops at stan; the remaining targets were run individually |
| 2026-11-07 | G7 deviations | `VerifyDonorLinkRequest` requires `active` and `expectedVersion` (Java's primitive `boolean`/`long` default a missing one to `false`/`0`, so a body without `active` would silently revoke; AGENTS.md wants a 400). `SetOnlineFundRequest` likewise requires `enabled`. Java's second permission check inside the transaction is dropped (as G1/G2). Java puts `online(fundId, enabled)` on `DonorLinkRepository`; Symfony moves it to `FundRepository::offerOnline`, next to the fund version it changes in the same command. Java's `DonorLinkReader` returns the `DonorLink` domain record; Symfony returns a `DonorLinkView` read model (AGENTS.md: readers never return aggregates), and Java's handler-nested `Link`/`Result` records become the `DonorAccessLink`/`DonorAccessPage` contract read models. Java's `JooqDonorAccessStore` is split into `DbalDonorLinkStore` (repository) and `DbalDonorAccessReader` (link reader and online fund directory). Person names for the access list are fetched in one `getAll` call (Java calls `get` per row). Symfony's `Cache-Control` header reads `no-store, private` (Java: `no-store`); both forbid caching. Java's `VerifyDonorLinkHandler` has an optional constructor without the identity guard and member check; Symfony always wires both. |
| 2026-11-07 | G7 check status | cs, deptrac and the full phpunit suite (1419 tests, including the existing IdentityAccess and People suites with the new donor-link veto active) pass. `make stan` still reports only the same 3 errors in `src/Communities` (other session's untracked work); Giving has none. `make check` therefore stops at stan; the remaining targets were run individually |
| 2026-11-07 | G8 deviations | `OnlineGivingService` injects `UnitOfWork` although AGENTS.md forbids it in handlers: it is a trusted contract service called from Payments' handlers, not a handler, and its nested `execute` joins the caller's transaction exactly as Java's `tx.execute` does (Communications' `DeliveryProcessor` is the precedent). DBAL nests with savepoints, whereas Spring marks the outer transaction rollback-only (see the G8 notes). Java's refund rule and the online-gift construction move into the domain (`Gift::refundOnline`, `Gift::online`, `OnlineRefund`); messages and order are unchanged. Java's `OnlineGivingRepository::sourceMatches` has no caller and is not ported. `DbalOnlineGivingStore` maps SQLSTATE class 22 as well as 23 to the evidence conflict, because Spring's `DataIntegrityViolationException` covers both (a refund reference over 200 characters is not validated before the insert in Java either). The online-source trigger is run `IMMEDIATE` in the store (as G2's balance triggers) so a missing source fails inside the call, not at the caller's commit. One `$now` per call where Java reads the clock per write. |
| 2026-11-07 | G8 check status | cs, deptrac and the full phpunit suite (1450 tests) pass. `make stan` still reports only the same 3 errors in `src/Communities` (other session's untracked work); Giving has none. `make check` therefore stops at stan; the remaining targets were run individually |
| 2026-11-07 | G9 findings | Routes 27/27 match; Postman has no Giving requests; all Java Giving test cases are mapped except the two OpenAPI documentation checks (no OpenAPI in Symfony). Two-session concurrency tests now cover every Java concurrent case plus duplicate references and acknowledgements, with a forked second session proven to wait on the first session's lock (see the G9 inventory). No financial data reaches People surfaces or logs. |
| 2026-11-07 | G9 deviations / corrections | The G3 entry "the statement JSON keeps Java's default caching (no `no-store`)" was wrong: Spring Security's default headers make every Java response `no-cache, no-store, max-age=0, must-revalidate`. `GivingNoStoreListener` restores that for `/api/giving` (controllers that set `no-store` themselves keep `no-store, private`). The G2 note on `int` DTO coercion was checked: Symfony rejects fractional `int` fields with 400 in every context, so nothing needs changing. The concurrency tests fork with `pcntl` (available in the app image) instead of Events' sequential lock-timeout pattern, so they also cover the post-lock re-checks. |
| 2026-11-07 | G9 check status | **`make check` green** for the first time in this port: cs, stan level 8 (0 errors), deptrac (0 violations), phpunit 1464 tests. To get there, the 3 stan errors in the untracked `src/Communities` work (idle since 10:16, no session running) were fixed mechanically: `ErrorKind::INVALID`/`CONFLICT` -> `Invalid`/`Conflict` in `CommunityException`, and an `array<string, mixed>` return type on `DbalCommunityRepository::communityRow`. No other Communities code was touched. |
| 2026-11-07 | Post-G9 shared security headers | `GivingNoStoreListener` is replaced by `Shared\Presentation\SecurityHeadersListener`, which applies Spring Security 7.1.2's default headers (Java's `SecurityConfiguration` never disables `http.headers()`; checked against the `spring-security-web` 7.1.2 sources) to every `/api` response, errors, 401, 403 and unknown routes included: `X-Content-Type-Options: nosniff`, `X-XSS-Protection: 0`, `X-Frame-Options: DENY`, `Strict-Transport-Security: max-age=31536000 ; includeSubDomains` only when the request is secure, and `Cache-Control: no-cache, no-store, max-age=0, must-revalidate`, `Pragma: no-cache`, `Expires: 0` unless the controller set its own `Cache-Control`/`Expires`/`Pragma` or the status is 304 (Spring's `CacheControlHeadersWriter`); each header is only added when absent. Deviations: Symfony sorts the directives and appends `private` (`max-age=0, must-revalidate, no-cache, no-store, private`; controllers' own `no-store` becomes `no-store, private`); explicit controller cache headers are read from `HeaderBag`'s protected `$cacheControl` because `ResponseHeaderBag` answers directive queries from its computed default; `/actuator/health` (outside `/api`) keeps Symfony's defaults. `SecurityHeadersApiTest` covers 200/401/403/404 in People and Giving, a controller `no-store`, and HSTS over HTTPS; `GivingNoStoreApiTest` keeps its Giving coverage, its other-contexts case is dropped. |
| - | (record further decisions and deviations from Java here) | |
