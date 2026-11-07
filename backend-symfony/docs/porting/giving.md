# Porting Giving to Symfony - technical approach and session plan

Status: **plan agreed (D1-D5 confirmed 2026-11-07); G0, G1 and G2 done. Next: G3-G7 (independent; G7 needs only G1).** Update the
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
| G3 | Reporting: totals, ledger lines, CSV export, donor statement, filters | `GivingReports`, `GivingFilters`, `JooqGivingLedger`, `GivingCsv`, `GivingReportsController` | M | G2 | [ ] |
| G4 | Pledges and assignments: create, cancel, assign gift, cancel assignment, candidates, list/get | `Pledge`, `PledgeAssignment`, `JooqPledgeStore`, `PledgeController` | L | G2 | [ ] |
| G5 | Acknowledgements: issue (explicit command, never a posting side effect), get, get by gift | `Acknowledgement`, `JooqAcknowledgementStore`, `AcknowledgementController` | S | G2 | [ ] |
| G6 | Reconciliation: create batch, review, list/get | `Reconciliation`, `JooqReconciliationStore`, `ReconciliationController` | M | G2 | [ ] |
| G7 | Donor identity and online-fund contracts: `DonorLink`, verify donor link, list donor access, `VerifiedDonorIdentity`, `DonorIdentityGuard`, `DonorPersonBindingPolicy`, set online fund, `OnlineFundDirectory` | `DonorLink`, `JooqDonorAccessStore`, `VerifiedDonorIdentity`, `DonorPersonBindingPolicy`, `DonorAccessController` | M | G1 | [ ] |
| G8 | Payments-facing ledger: `OnlineGivingLedger` (validate, post, refund), `DonorCheckoutGuard`, `giving_payment_sources`/`refunds` | `OnlineGivingService`, `JooqOnlineGivingStore`, `PublishedDonorCheckoutGuard` | S | G2, G7 | [ ] |
| G9 | Hardening: parity sweep vs Java tests and Postman, trigger tests, concurrency tests, security review, README/AGENTS updates | all | M | G1-G8 | [ ] |

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

### G4 - Pledges
Deliver: pledge create (period at most 3,660 days, fund and currency), cancel with reason, assign gift allocation to a pledge with
available and remaining-target math, cancel assignment, candidate gifts list. Prove: `validateAssignment` cases, parallel assignments
cannot over-credit, cancelled pledge refuses assignment.

### G5 - Acknowledgements
Deliver: issue for a gift (frozen line items, balance trigger), get by id and by gift. Prove: immutability; acknowledging a reversed gift
behaves as Java does (check the handler).

### G6 - Reconciliation
Deliver: batch with reference uniqueness, gifts, expected vs actual, explanation when different; reviews with outcome and note; list/get.
Prove: duplicate gift ids rejected, over 100 rejected, balance trigger, reversed amounts reported.

### G7 - Donor identity
Deliver: `DonorLink` verify/revoke with history, `GET/PUT /api/giving/donor-access`, `PUT /api/giving/online-funds/{fundId}`,
`DonorIdentity`/`DonorIdentityGuard`, `DonorPersonBindingPolicy`, `OnlineFundDirectory`, `Cache-Control: no-store`. Prove: link cannot be
reassigned to another person, household/email matches grant nothing, guard lock precedes People and fund locks.

### G8 - Payments-facing ledger
Deliver: `OnlineGivingLedger` over `giving_payment_sources`/`giving_payment_refunds`, idempotent on `chargeId` and `refundId`; manual reversal of
an online gift rejected; `DonorCheckoutGuard`. Prove with a contract test written as Payments would call it.

### G9 - Hardening
Deliver: parity and trigger test suite, concurrency tests (parallel assignments, parallel reversals of one gift, duplicate references),
security review (no financial data on People surfaces or in logs), README/AGENTS updates.

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
| - | (record further decisions and deviations from Java here) | |
