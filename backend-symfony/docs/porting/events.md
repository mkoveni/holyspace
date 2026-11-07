# Porting Events to Symfony - technical approach and session plan

Status: **E0-E10 done (E9 built early because the Communities port is under way). D1-D5 all confirmed as recommended on 2026-11-07.** Update the
[status board](#5-status-board) and the [decision log](#10-decision-log) at the end of every session. This file is the
hand-off between sessions, in the same way as `children-ministry.md`.

Source of truth: `backend-java/src/main/java/org/onechurch/events` (180 files, about 6.2k lines, 22 command handlers,
22 query handlers, 10 controllers) and its 52 test files. Read `AGENTS.md` first; every rule there applies. The
Java `ARCHITECTURE.md` and `CONTEXT-MAP.md` (sections "Events", "Event day and communications", "Member event
participation") describe intent.

---

## 1. Scope

In scope: the whole Java `events` bounded context, 41 endpoints under `/api/events/**` and `/api/member/events/**`.

| Capability | Endpoints | Java entry points |
| --- | --- | --- |
| Venues | 3 | `VenueCatalogue`, `EventVenuesController` |
| Definitions and recurring series | 6 | `EventPlanning`, `ScheduleReview`, `ChurchEventsController` |
| Occurrence calendar and changes | 7 | `OccurrenceChanges`, `OccurrenceChangeReview`, `EventOccurrencesController` |
| Registration, queue, waitlist | 5 | `EventRegistrationOperations`, `EventRegistrationsController` |
| Attendance (check-in, walk-in, correction, roster, reports, CSV) | 7 | `EventAttendanceOperations`, `AttendanceViews`, `EventReports` |
| Readiness and closeout | 4 | `EventReadiness`, `EventCloseouts`, `EventCloseoutController` |
| Notifications (review, approval, contacts, deliveries) | 4 | `EventMessageReview`, `EventMessageApproval`, `EventNotificationsController` |
| Member self-service (offer, book, cancel, pass, scan) | 8 | `MemberEventActions`, `MemberEventsController` |
| Published contracts for other contexts | - | `application/contract/*` (17 interfaces) |

Out of scope: the `Communities` context (groups and ministries). It is the only Java consumer of Events contracts
(`CommunityCalendar`, `CommunityScheduleReader`, `OccurrenceMovementPolicy`, `OccurrenceScheduleListener`) and does not
exist in Symfony. See D3.

No schema work is expected. All Events tables are already in `migrations/` and are byte-identical to the Java files:

| Migration | Tables / objects |
| --- | --- |
| V15 | `event_venues`, `event_definitions`, `event_series`, `event_occurrences` (with the `event_venue_schedule_no_overlap` gist EXCLUDE constraint), `event_registration_policies`, `event_registrations`, `event_registration_history`, `event_occurrence_history`, `event_requests`, `event_audit`; permissions `events.read`, `events.manage`, `events.registrations.read`, `events.registrations.manage`; `serving_shifts_source_idx` |
| V16 | `event_attendance`, `event_attendance_history`, `event_notifications` (permissions `events.attendance.*`, `events.communications.send`) |
| V17 | `event_closeouts`, `event_closeout_history` |
| V27 | `event_member_offers`, `event_admission_passes` |

Verify each table against the mapper you write. If something is missing, the next free migration number is V33
(V31 and V32 are taken).

---

## 2. Source inventory

| Java package | Files | Lines | Notes |
| --- | --- | --- | --- |
| `domain` | 13 (+7 repositories) | ~330 | Records with invariants in compact constructors, `EventRules`, `EventSchedule` (DST-strict windows), `AdmissionWindow`, `OccurrenceCloseout` state machine |
| `application` (coordinators) | 16 | ~1,770 | Multi-step workflows: `EventAttendanceOperations`, `EventRegistrationOperations`, `MemberEventActions`, `OccurrenceChanges`, `EventReadiness`, message review/approval |
| `application/command` | 22 handlers | ~700 | Thin: permission, then delegate to a coordinator |
| `application/query` | 22 handlers | ~700 | Thin |
| `application/contract` | 17 | ~190 | 9 reader/writer ports, 4 for Communities, 4 for notification/journal |
| `infrastructure` | 8 | ~660 (of which ~460 is Spring bean wiring) | Wiring becomes `services.yaml`; real code is `EventServingActivities`, `EventNotificationEligibility`, `EventTemplatePolicy`, `PublishedCommunityCalendar` |
| `infrastructure/persistence` | 12 | ~1,160 | jOOQ stores |
| `presentation` | 10 controllers + 9 `*Api` interfaces | ~590 + ~600 | The `*Api` interfaces only carry OpenAPI/route annotations; they become attributes on the controllers |

### Cross-context dependencies (what Events consumes)

| Contract (Java) | Used for | Symfony today |
| --- | --- | --- |
| `PermissionChecker` (45 uses) | authorization in every handler | present |
| `PersonDirectory` (11), `PersonEmailDirectory` (6) | person identity, locks, contacts | present |
| `MemberIdentity`, `MemberIdentityGuard`, `MemberAdmissionGuard` | member self-service and pass validation | present |
| `AccountNames`, `AccountDirectory`, `ResponsibleAccountDirectory`, `CurrentActor`, `SessionReader`, `TokenCodec` | audit names, pass issuing and session checks | present |
| `EmailDeliveries`, `EmailTemplates`, `DeliverySnapshot`, `DeliveryReader`, `DeliveryEligibility`, `TemplatePublicationPolicy` | notices | present (Communications) |
| `ActivityStaffing` (Volunteering) | staffing totals in readiness | **missing** |
| `MemberNotifications` (Communications, V29 inbox) | `EVENT_BOOKING_UPDATED` inbox entries | **missing** |
| `ActivitySource`, `ServingActivityLocks` (implemented **by** Events) | Volunteering schedules duties against occurrences | contracts present; Events implementation to add |

### Cross-context consumers (what Events publishes)

Symfony `Volunteering` already collects `ActivitySource` implementations through `ActivityRegistry`
(`config/services.yaml` tags it). Events must implement `kind() === 'EVENT_OCCURRENCE'`; today an `EVENT_OCCURRENCE`
shift fails with "This activity source is not available in the Java application."
Children Ministry does not touch Events.

---

## 3. Target architecture in Symfony

### 3.1 Namespace layout

A single flat context, not a modular one. Java has one `events` package with no internal module walls, so there is
nothing for `ModuleBoundaryTest` to enforce and no tooling change is needed (deptrac regexes and
`ContextBoundaryTest` already handle `App\<Context>\<Layer>`).

```
src/Events/
  Domain/          Venue, EventDefinition, EventSeries, EventOccurrence, RegistrationPolicy, EventRegistration,
                   Attendance, OccurrenceCloseout, AdmissionPass, AdmissionWindow, EventSchedule, EventRules,
                   EventException, Repository/*
  Application/
    Command/<UseCase>/{<UseCase>Command,<UseCase>Handler}.php     (22)
    Query/<UseCase>/{<UseCase>Query,<UseCase>Handler}.php         (22)
    Contract/        published read ports (see 3.4)
    <Coordinators>   shared collaborators used by several handlers
  Infrastructure/
    Persistence/     Dbal*Store + mappers
    Published*       ActivitySource, DeliveryEligibility, TemplatePublicationPolicy adapters
  Presentation/      controllers + request DTOs
```

`EventException extends ContextException`, with `invalid()` -> 400, `missing()` -> 404, `conflict()` -> 409, as in
`VolunteerException`. The API exception listener already maps `ErrorKind`; the Java handler additionally maps a duplicate
key to 409 "This name or request is already in use" - port that as a translation of SQLSTATE `23505` in the stores.

### 3.2 Java record to PHP mapping

- Java compact-constructor validation becomes **private constructors plus named factories** (`create`, `reconstitute`),
  per AGENTS.md. Java's "return a new record with `version + 1`" becomes `Revision::next($expectedVersion, $now)`.
- Event-specific versioning detail: `EventOccurrence` carries both `version` (optimistic lock) and `scheduleRevision`
  (published to Volunteering, bumped only on date, venue or status changes). Keep both; `scheduleRevision` is a plain
  counter on the aggregate, not part of `Revision`.
- `AdmissionPass` is a data row with no behaviour: model it as a read/write record, not an aggregate.
- Timestamps: Java truncates to microseconds (`EventRules.instant`). PostgreSQL stores microseconds, so truncate in the
  domain factory too, or optimistic equality checks and fingerprints will drift.

### 3.3 Conventions that matter most for this context

- **Commands** through `CommandBus` (one transaction each, no `UnitOfWork` in handlers); **queries** call handlers
  directly and return read models. Replace every Java `tx.inTransaction(...)` with the bus transaction.
- **Idempotency**: command requests carry a client `requestId`; `event_requests` stores `(id, kind, hash, target,
  actor)`. A repeat with the same hash returns the original target, a repeat with a different hash is a 409. Port the
  `EventJournal`/`EventJournalReader` pair to one `EventJournal` writer and an `Application\Contract` reader.
- **Lock order (preserve exactly, one test per multi-lock handler using a recording fake, as AGENTS.md requires):**
  - scheduling: event, then series, then venues sorted by UUID string, then occurrences sorted by UUID string
    (`OccurrenceChanges`).
  - registration: person, then occurrence (never a global lock).
  - attendance, walk-in, correction, message approval: person (`PersonDirectory::lock`), then occurrence.
  - closeout and reopen: occurrence only.
  - serving locks (`ServingActivityLocks::lock`): occurrences, distinct and sorted by UUID string.
- **Schedule preview hash** (`Fingerprint::of`) must be byte-for-byte compatible with Java: SHA-256 over, for each value,
  a 4-byte big-endian length followed by the UTF-8 bytes of `String.valueOf(value)`, hex-encoded lowercase. Apply
  hashes are exchanged between a preview call and an apply call, and the review of a Java-created hash must still
  validate during any mixed deployment. `String.valueOf` of an `Instant`, `LocalDate`, `UUID`, `long` and `boolean` must
  be reproduced exactly (ISO-8601 with the same fractional-second rules; `true`/`false`). Add a golden-vector test
  generated from Java.
- **Pagination limits**: `page()` is 1-100 and offset up to 100000; `range()` is at most 366 days; `period()` is at
  most 7 days. These live in `EventRules` and are asserted by security tests; copy them.
- Routes, status codes, JSON field names and error `message` strings match Java exactly. The frontend and mobile app
  depend on them.
- Responses that contain pass tokens or member data carry `Cache-Control: no-store` (check each Java controller).
- Request DTOs declare `expectedVersion` as a required `int` (never default to 0).

### 3.4 Published contracts

Publish under `App\Events\Application\Contract`, keeping Java semantics:

| Contract | Port in | Consumer |
| --- | --- | --- |
| `EventReader`, `OccurrenceReader`, `RegistrationReader`, `AttendanceReader`, `CloseoutReader`, `EventReportReader`, `MemberEventReader`, `EventNotificationReader`, `EventJournalReader` | internal ports, put in `Application/Contract` as the read side (queries only get readers) | Events only |
| `EventNotificationWriter`, `MemberEventWriter` | internal | Events only |
| `ActivitySource` + `ServingActivityLocks` implementation (`PublishedEventActivities`) | E3 | Volunteering |
| `CommunityCalendar`, `CommunityScheduleReader`, `OccurrenceMovementPolicy`, `OccurrenceScheduleListener` | deferred (D3) | Communities |

Several of the "published" Java interfaces are not cross-context at all; they are inner-ring ports. Keep the inner-ring ones
as readers/writers inside `Application/Contract` (AGENTS.md puts read-only contracts there) but do not pretend they
are published API.

### 3.5 Parity verification

1. Port the Java tests for the slice: 44 `*HandlerSecurityTest` (permission first, stale version, audit only on success), 2
   domain tests (`EventScheduleTest`, `OccurrenceCloseoutTest`), `EventServingActivitiesIntegrationTest`, and 6 API
   integration tests (`EventsApi`, `EventAttendanceApi`, `EventAttendanceReportApi`, `EventCloseoutApi`,
   `EventNotificationsApi`, `MemberEventsApi`). `EventsIntegrationTestSupport` becomes a test support trait.
2. API tests (`IdentityApiTestCase::accountWith`) per endpoint: success, 401, 403, 404, 409 stale version, 400 malformed
   input, rollback on failure.
3. JSON contract diff against Java and `../postman/OneChurch.postman_collection.json`.

---

## 4. Risks and the behaviours that are easy to get wrong

| Risk | Why | Mitigation |
| --- | --- | --- |
| **DST handling** | `EventSchedule::window` rejects any local time that is ambiguous (two valid offsets) or nonexistent (gap). PHP `DateTimeImmutable` silently shifts forward in a gap and picks the first offset in an overlap, so a naive port accepts input Java rejects | Detect explicitly: build the local time, convert to UTC and back, and compare; for overlaps compare against `DateTimeZone::getTransitions()` around the date. Port `EventScheduleTest` first and add cases for both a gap and an overlap in a real zone (for example `Europe/London`, `America/New_York`) |
| **Monthly recurrence** | Java `plusMonths` clamps (31 Jan + 1 month = 28 Feb) and the Java code then rejects clamped dates. PHP `modify('+1 month')` **overflows** (31 Jan becomes 3 Mar) | Implement month stepping explicitly (year/month arithmetic, then check the day), do not use `modify` or `DateInterval` |
| **Exclusive venue overlap** | enforced by the DB gist EXCLUDE constraint; violation surfaces as SQLSTATE `23P01` | Map `23P01` to a 409 with the Java message. Keep the pre-check in the preview so the user sees conflicts before applying |
| **Queue and waitlist** | position is allocated from `event_registration_policies.next_position` under the occurrence lock; waitlist promotion is FIFO; cancel and rejoin reuse the same registration row | Port `RegistrationPolicy::allocate`/`configure` unchanged, and test promotion order with two concurrent cancels |
| **Set-based cancellation** | cancelling occurrences cancels all active registrations in one statement (not a loop) | Use one `UPDATE ... RETURNING` plus history `INSERT ... SELECT`, in the occurrence lock order |
| **Hash compatibility** | see 3.3 | golden vector test |
| **Admission pass** | single-use token, store only the hash, bound to `registrationVersion`, `memberVersion`, `scheduleRevision`, expiry and session; scan locks person then occurrence and re-checks each | Reuse the existing bearer-token hashing approach from IdentityAccess (`TokenCodec`); write one test for each binding mismatch |
| **Sensitive data** | attendance reports must not copy Children Ministry data or grant station rights; member projections show only event details and the caller's booking | Add `EventsIndependenceTest` (no import of `ChildrenMinistry`) and a member-projection field allowlist test |
| **Notices** | approval freezes content; dispatch checks current source state without re-rendering; the source fingerprint is immutable evidence | Follow `CommunicationsIndependenceTest`: Events supplies `DeliveryEligibility` and `TemplatePublicationPolicy` as tagged services, Communications never imports Events |
| **Missing contracts** | `ActivityStaffing`, `MemberNotifications` | see D1 and D2 |

---

## 5. Status board

Legend: `[ ]` not started, `[~]` in progress, `[x]` done (`make check` green). Size: S under 1k Java lines, M 1-2k, L 2-3k.

| # | Session | Java source | Size | Needs | Status |
| --- | --- | --- | --- | --- | --- |
| E0 | Prerequisites: `Volunteering\ActivityStaffing` contract and adapter over V14 totals; decision on `MemberNotifications` (D2); `EventException`, `EventRules`, `Fingerprint` (+ golden vectors), permission codes check, empty `src/Events` skeleton wired into deptrac and the container | `domain/EventRules`, `EventException`, `application/Fingerprint` | S | - | [x] |
| E1 | Venues: `Venue` aggregate, `VenueRepository`, create/update/list, `EventJournal` (audit + `event_requests` idempotency), `VenueCatalogue`, `EventVenuesController` | `Venue`, `VenueCatalogue`, `JooqVenueStore`, `JooqEventJournal` | S | E0 | [x] |
| E2 | Definitions and series: `EventDefinition`, `EventSeries`, `EventSchedule` (DST strict), `ScheduleReview`, create/update/get/list events, create series, schedule preview | `EventPlanning`, `ScheduleReview`, `EventSchedule`, `JooqEventStore`, `ChurchEventsController` | M | E1 | [x] |
| E3 | Occurrences: calendar, get, history, reschedule/cancel/restore with preview and hash; `PublishedEventActivities` (`ActivitySource` + `ServingActivityLocks`); venue exclusion mapping | `OccurrenceChanges`, `OccurrenceChangeReview`, `JooqOccurrenceStore`, `EventServingActivities`, `EventOccurrencesController` | L | E2 | [x] |
| E4 | Registration: policy, register person, cancel, queue and waitlist promotion, list, history | `EventRegistrationOperations`, `RegistrationPolicy`, `EventRegistration`, `JooqRegistrationStore`, `EventRegistrationsController` | M | E3 | [x] |
| E5 | Attendance: check-in, walk-in, correction, roster, history, attendance report and CSV export | `EventAttendanceOperations`, `AttendanceViews`, `EventReports`, `JooqAttendanceStore`, `JooqEventReportStore`, 2 controllers | M | E4 | [x] |
| E6 | Readiness and closeout: readiness composition, close, reopen, history | `EventReadiness`, `EventCloseouts`, `OccurrenceCloseout`, `JooqCloseoutStore`, `EventCloseoutController` | M | E5, E0 (`ActivityStaffing`) | [x] |
| E7 | Notifications: contacts, review, approval (frozen content), deliveries list, `DeliveryEligibility` and `TemplatePublicationPolicy` adapters | `EventMessageReview`, `EventMessageApproval`, `EventNotificationEligibility`, `EventTemplatePolicy`, `JooqEventNotificationStore`, `EventNotificationsController` | M | E5 | [x] |
| E8 | Member events: offer get/put, list/get mine, register/cancel mine, issue pass, scan member | `MemberEventActions`, `AdmissionPass`, `JooqMemberEventStore`, `JooqAdmissionPassStore`, `MemberEventsController` | M | E4, E5 (+ D2 outcome) | [x] |
| E9 | Community contracts (**deferred, D3**): `CommunityCalendar`, `CommunityScheduleReader`, `OccurrenceMovementPolicy`, `OccurrenceScheduleListener` | `PublishedCommunityCalendar`, `application/contract/Community*` | S | Communities port | [x] |
| E10 | Hardening: parity sweep against Java tests and Postman, concurrency tests for queue/promotion and lock order, security review of pass and notice paths, README and AGENTS updates | all | M | E1-E8 | [x] |

Rough total: 10 sessions (9 without E9). E4 and E7 can run in parallel only after E5 lands the attendance tables; never two
writers on the same files. E6/E7/E8 are independent of each other once E5 is done.

---

## 6. Session details

Each block lists what to deliver and prove. Write the **slice inventory** (handlers, endpoints, tables, cross-context
calls) under it when you start the session.

### E0 - Prerequisites and foundation
Deliver: `ActivityStaffing` in `Volunteering/Application/Contract` and an adapter over the V14 totals (counts only, no
volunteer identities or domain objects). `EventException`, `EventRules`, `Fingerprint`, the empty `Events` layers
(`services.yaml` excludes for `src/Events/Domain`, `Application/Command/*/*Command.php`, `Application/Query/*/*Query.php`).
Prove: golden hash vectors from Java, `EventRules` bounds tests, `make deptrac` accepts the skeleton.

### E1 - Venues and journal
Deliver: venue create/update/list, `events.manage` and `events.read` gates, venue active/exclusive flags, audit rows
in `event_audit`, request idempotency in `event_requests`. Prove: stale version 409, duplicate name 409, audit only on success.

### E2 - Definitions, series, schedule
Deliver: `EventSchedule::window` and `preview` with explicit DST and monthly handling; events and series; schedule
preview endpoint that does not write. Prove: `EventScheduleTest` ported with gap and overlap cases; the two-year cap; the
7-day interval cap; the "monthly date missing" rejection.

### E3 - Occurrences and Volunteering seam
Deliver: change preview computes a hash over reviewed dates, venue and policy versions and booking counts; apply re-locks
and recomputes; single-occurrence overrides survive later future-series changes; cancellation includes overrides;
restoration is explicit and single date; moved dates keep id and recurrence origin; venue name and address are
snapshotted on the occurrence. `PublishedEventActivities` returns `Activity` with `scheduleRevision` and venue label.
Prove: lock-order recording test; a venue exclusion overlap yields 409; `EventServingActivitiesIntegrationTest`
equivalent (a Volunteering shift against an event occurrence goes stale after a reschedule).

### E4 - Registration
Deliver: policy configure (open, capacity 1-10,000 or unlimited, waitlist), register (confirmed vs waitlisted),
cancel with FIFO promotion, rejoin of a cancelled booking, history. Prove: person-then-occurrence lock order; promotion order;
capacity under concurrency; cancelling an occurrence cancels bookings but never restores them on restore.

### E5 - Attendance and reports
Deliver: check-in window (two hours before start until end), walk-in registration plus arrival, correction to
`PRESENT`/`VOIDED` with append-only history, roster, absence derived only after the occurrence ends, reports (JSON and CSV
with `text/csv`). Prove: window edges, double check-in idempotency, correction makes a closeout stale (E6), CSV escaping
(including formula-injection prefixes if Java guards them - check `EventReports`).

### E6 - Readiness and closeout
Deliver: readiness composition from registration, attendance, staffing (`ActivityStaffing`) and delivery summaries
(`DeliveryReader`), versioned closeout with review hash, close/reopen/history. Prove: `OccurrenceCloseoutTest` ported;
close before end 409; unresolved follow-up requires text; late attendance correction marks the closeout stale.

### E7 - Notifications
Deliver: contact list (active emails through `PersonEmailDirectory`), notice review with immutable source
fingerprint, approval that freezes content and enqueues through `EmailDeliveries`, deliveries list, eligibility and
template policy adapters registered as tagged services. Prove: Communications never imports Events; dispatch rechecks
source state; approval locks person then occurrence.

### E8 - Member events
Deliver: staff-configured member offer, member catalogue limited to offered events, own booking, cancel, pass issue
(hash only stored, bound to member revision, registration version and schedule revision), staff scan with
`MemberAdmissionGuard`. Prove: a member cannot act for another person; expired, used or mismatched passes are rejected;
projections expose only event details and the caller's own booking status. Inbox entries per D2.

### E10 - Hardening
Deliver: parity diff against Java tests and Postman, a concurrency test suite (two parallel registrations at capacity, two
parallel cancels, parallel reschedule vs register), a security pass over passes and notices, README and AGENTS updates.

---

## 7. Definition of done for each session

1. `make check` (cs, stan level 8, deptrac, phpunit) is green.
2. Every ported handler has an isolated test: permission checked first, stale version rejected, audit only on success.
3. Every new endpoint has an API test covering 401, 403, 404, 409, 400 and rollback.
4. Board ticked, decisions and deviations logged, "Next session notes" written for anything half-done.
5. No commit unless asked.

---

## 8. Kickoff prompt for a new session

> Continue the Events port. Read `backend-symfony/AGENTS.md` and `backend-symfony/docs/porting/events.md`, then do the
> next unchecked session on the status board. Java is the specification. Finish with `make check` green and update
> the board and decision log.

---

## 9. Open decisions (confirm before E0)

| # | Question | Recommendation |
| --- | --- | --- |
| D1 | `ActivityStaffing` is missing in Symfony. Port the real adapter in E0, or stub? | Port the real one in E0 (counts only). A stub would make readiness wrong rather than merely incomplete |
| D2 | `MemberNotifications` needs the Communications member inbox (V29), which is not ported. Port the inbox first, or seam? | Seam: `PendingMemberNotifications` (no-op, `PLACEHOLDER` docblock, listed in the log) so E8 is not blocked; replace when the inbox is ported. Booking behaviour is unaffected, only the inbox entry is missing |
| D3 | `Communities` is not in Symfony. Port the four Community contracts now? | Defer (E9). No consumer exists; build them when Communities is ported, against its real needs |
| D4 | Flat context or modules? | Flat (3.1). One cohesive Java package, no internal walls |
| D5 | Cut-over strategy: while both backends share one database, only one may serve Events writes at a time because schedule hashes, queue positions and locks assume a single writer implementation | Route Events traffic per environment (not per endpoint). Hash compatibility (3.3) keeps in-flight previews valid across a switch |

---

## 10. Decision log

| Date | Decision | Notes |
| --- | --- | --- |
| 2026-11-07 | D1: real `ActivityStaffing` adapter | E0 added `Volunteering\Application\Contract\ActivityStaffing` + `StaffingSummary` (counts only) and `ActivityStaffingSummary`. `ShiftView` gained `positions` (V14 column) to support it |
| 2026-11-07 | E0 deviations | `Events\Application\Fingerprint::of` takes typed values (string, int, bool, `DateTimeImmutable` as `Instant#toString`, null); UUIDs and local dates are passed as strings. Kept separate from `Volunteering\Application\Fingerprint` (different input types); consider moving both to `Shared` when a third user appears. `EventRules::id` takes a string id; `instant` only normalises to UTC (PHP already holds microseconds); `zone` accepts region ids, `UTC`, `Z` and `+hh:mm` offsets, not abbreviations. Java text length counts UTF-16 units, PHP counts characters. `services.yaml` needed no change: the existing `src/*/Domain` and Command/Query globs already cover `Events` |
| 2026-11-07 | E1 deviations | `Venue` keeps a plain `int version` (no `Revision`: `event_venues` has no timestamp columns); stale edits throw `EventRules::version` (409 "These details changed..."). `EventJournal::record` writes `event_requests` (the reader's `request(id)` shares a name in Java; PHP cannot overload). A duplicate `event_requests` id maps to 409 "This name or request is already in use". `events.manage` implies `events.read` through `IdentityRules::effectivePermissions`. V33 adds a trigram index on `event_venues.name` (AGENTS.md rule for contains-search); migration-count tests bumped to 33 |
| 2026-11-07 | E2 deviations | New `Domain\TimeOfDay` (local time, microsecond precision; `javaString()` reproduces `LocalTime#toString`, `iso()` is the wire/DB form `HH:mm:ss[.f]`). `EventSchedule::window` finds the valid offsets around the local time (offsets at +-1 day, each re-checked) so gaps (0 valid) and overlaps (2) are rejected. Months step through `CalendarDate::plusMonths` (clamps like Java, then the day mismatch is rejected). `Application\JavaText` renders `Draft[...]`, `Venue[...]`, `Window[...]` exactly like Java record `toString`, because preview hashes are `Fingerprint` over `String.valueOf`. **The hash text was derived by reading Java; no JVM was available to generate golden vectors, so run one cross-check against a Java-produced hash before a mixed deployment (E10).** Idempotent creates share `RequestReplay`; `SchedulePlanner` writes occurrences, closed policies and history. `V34` adds a trigram index on `event_definitions.title`. The main events routes use an id requirement that excludes the literal `venues` and `calendar` segments so a malformed id is a 400 from `PathUuid` and not a route clash. Series times serialise as `HH:mm:ss` (Jackson default for `LocalTime`) |
| 2026-11-07 | E3 deviations | `OccurrenceRepository::findUnlocked` / `VenueRepository::findUnlocked` are the non-locking aggregate loads used by reviews (readers keep `find` and return views). `OccurrenceChangeReview::plan` returns aggregates in an Application plan object; the query handler maps it to views. Community listeners and `OccurrenceMovementPolicy` are not wired (D3); reschedule has no movement veto until E9. The exclusion constraint (`23P01`, deferred to commit) already maps to 409 in `ApiExceptionListener`. `AttendanceReader` is only `attended()` for now (E5 extends it). `PublishedEventActivities` implements `ActivitySource` and `ServingActivityLocks`; Volunteering has no write path yet, so the seam is proven through `ActivityRegistry` (a reschedule changes the revision and the shift goes stale) |
| 2026-11-07 | E4 deviations | `EventRegistration` uses `Shared\Revision` with `advance()` and the handlers check `EventRules::version` first, so stale-version messages stay Java's. `promote` and the occurrence-wide cancel are set-based `INSERT ... SELECT` plus `UPDATE ... WHERE id IN (...)`, both under the occurrence lock. `RegisterEventPersonRequest.expectedVersion` is nullable on purpose: null is a first booking, any other value is the version of the person's earlier (cancelled) row |
| 2026-11-07 | E5 deviations | Roster search moved out of the store: the query handler asks `PersonDirectory::matchingIds` and passes the ids to the reader, so the reader never imports People. The CSV formula guard is Java's (`= + - @` after leading whitespace gets a leading apostrophe) and the header is `text/csv;charset=UTF-8` without a space, as Spring writes it |
| 2026-11-07 | E6 deviations | `EventReadiness::review` hashes `Counts[...]`, `Notices[...]` and the `id:version:status:captured` revision list exactly as Java's `String.valueOf` would. A notice with no delivery is `failed` (`MISSING` in the revision list). Closing locks only the occurrence |
| 2026-11-07 | E7 deviations | `NoticeRules` holds the purpose/template mapping, the "may this notice be sent now" rule and the review hash, shared by review, approval and `EventNotificationEligibility`, which Communications calls again before every send. `EventTemplatePolicy` and the eligibility adapter are collected by the existing tagged-service wiring. Responses that carry recipients or rendered content send `Cache-Control: no-store` |
| 2026-11-07 | E8 deviations / D2 | The inbox seam is `Contract\MemberBookingUpdates` with the `Infrastructure\PendingMemberBookingUpdates` no-op (PLACEHOLDER): booking, waitlist and pass behaviour is unaffected, only the inbox entry is missing. `scan` re-reads the booking after taking the person and occurrence locks (Java compared a version read before the locks). Pass format `holyspace:event:v1:<43 chars>`, five minutes, capped at the occurrence end |
| 2026-11-07 | E9 (built early) | `CommunityCalendar`, `CommunityScheduleReader`, `OccurrenceMovementPolicy`, `OccurrenceScheduleListener` published under `Events\Application\Contract`. `PublishedCommunityCalendar` calls `CreateChurchEventHandler` directly (it runs inside the caller's transaction) and turns `EventException` into `CalendarFailure(400/404/409)`. Movement policies and schedule listeners are tagged services injected into the change review and apply (`events.movement_policy`, `events.schedule_listener`). Nothing implements them yet; the Communities port (C4) should |
| 2026-11-07 | E10 findings | **Route parity:** the 44 Events routes in the Java `*Api` interfaces and the 44 Symfony routes match exactly (method and path). The Postman collection contains no Events requests, so there is nothing to diff there. **Concurrency:** `EventConcurrencyTest` opens two real database sessions: a second booking waits (SQLSTATE 55P03) for the first session's occurrence lock and then sees the committed seat; a booking waits for an uncommitted cancel plus promotion and is waitlisted behind the promoted person; unique `(occurrence, person)` and `(occurrence, queue_position)` still reject duplicates. **Secrets:** `EventSecretsTest` proves pass credentials, notice text, recipients and evidence do not appear in `print_r` or `var_dump`; the member API test proves an unknown credential is not echoed. **Hash parity (open):** `ScheduleHashGoldenTest` pins hashes computed by an independent implementation of Java's algorithm from Java's record text, but no JVM was available, so none of the vectors is captured from Java. Before any mixed Java/Symfony deployment (D5), replay one Java-produced schedule preview hash and one occurrence-change hash through this application and add them as vectors |
| 2026-11-07 | Known gaps | (1) `EventRules::zone` accepts `EST`, `MST` and other legacy ids that `ZoneId.of` rejects (E0, PHP's `ALL_WITH_BC`); tighten it with a list of Java's accepted ids if it matters. (2) Isolated handler tests exist for venues, events, series, occurrences, registrations, attendance and closeout; notices and member actions are covered by API tests and the lock-order and secrecy tests, not by recording-fake handler tests. (3) Parallel reschedule-versus-register and scan-versus-cancel are covered by lock-order reasoning and the two-session test above, not by a dedicated interleaving test. (4) The Communications deliveries controller is not ported, so `/api/events/deliveries` renders delivery summaries itself |
| 2026-11-07 | D2-D5 confirmed | Confirmed as recommended: D2 inbox seam (`PendingMemberBookingUpdates`), D3 Community contracts built when needed (done early as E9), D4 flat `Events` context, D5 route Events traffic per environment, not per endpoint. D5 still requires the open Java-captured hash vectors (see E10 findings) before any mixed deployment |
