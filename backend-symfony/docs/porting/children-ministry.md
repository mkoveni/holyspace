# Porting Children Ministry to Symfony - technical approach and session plan

Status: **plan agreed (D1, D2, D3, D5 decided; D4 decided in C7a), C1, C2, C3a-d, C4, C5, C6a-c, C7a-b, C8, C9, C10, C11, C12, P3 done. The Children Ministry port is complete.** Update the [status board](#4-status-board) and the
[decision log](#9-decision-log) at the end of every session. This file is the hand-off between sessions.

Source of truth: `backend-java/src/main/java/org/onechurch/childrenministry` (about 13.6k lines, 99 handlers,
6 modules) and its 78 tests. Read `AGENTS.md` first; every rule there applies to this port.

---

## 1. How to use this document across sessions

Every session starts the same way:

1. Read `AGENTS.md`, this file, and the status board. Pick the **first unchecked session** whose prerequisites are done.
2. Read only the Java sources listed for that session (and their tests). Do not read other modules "for context".
3. Write the **slice inventory** into the session's section below before coding (handlers, endpoints, tables, cross-module calls).
4. Port bottom-up: Domain, then Application (contracts, read models, commands, queries), Infrastructure, Presentation, tests.
5. Run `make check` (cs, stan level 8, deptrac, phpunit). It must pass before the session ends.
6. Tick the board, add decisions and deviations to the log, and write "Next session notes" for anything half-done.
7. Do not commit unless asked. Do not edit applied migrations; new schema needs V33+ (V31 and V32 are taken).

Kickoff prompt to paste into a new session:

> Continue the Children Ministry port. Read `backend-symfony/AGENTS.md` and
> `backend-symfony/docs/porting/children-ministry.md`, then do the next unchecked session on the status board.
> Java is the specification. Finish with `make check` green and update the board and decision log.

A session is sized to fit one working sitting (roughly 1.5-3k lines of Java ported with tests). If a session
runs long, stop at a green `make check`, record the remainder in "Next session notes" and split the row.

---

## 2. Source inventory

| Module | Files | Lines | Owns |
| --- | --- | --- | --- |
| shared | 9 | 324 | Ministry rules, failures, locking, auditing, record mapping |
| participation | 49 | 1,640 | Programmes, classes, rooms, locations, enrolment, placements |
| safeguarding | 55 | 2,144 | Guardian authority, consent policies and evidence, collector grants, confidential support, readiness |
| sessions | 165 | 6,239 | Recurring series, teams, staffing, admission, attendance, handover, closeout, workers, member family views |
| incidents | 26 | 1,081 | Restricted incidents, assignment, follow-up, closure, retention |
| messaging | 44 | 1,587 | Guardian eligibility, contact verification, message approval |
| reporting | 23 | 619 | Read-only attendance reports and ministry overview |

Migrations already present in `migrations/`: V3 (participation and consent), V4 (sessions and staffing), V5 (pickup
authorizations), V6 (attendance and support), V7 (roster index), V9 (recurring series), V10 (team planning index),
V11 (incidents and communication), V12 (guardian email delivery), V28 (member family authority index).
No children schema work is expected, but verify each table against the mapper you write.

### Cross-module imports in Java (the coupling to respect)

```
sessions      -> participation (51), safeguarding (20), incidents (4), reporting (2)
participation -> safeguarding (13), sessions (12)
safeguarding  -> participation (17), sessions (18)
messaging     -> participation (7), safeguarding (7), sessions (4)
incidents     -> safeguarding (7), sessions (5)
reporting     -> sessions (6), safeguarding (3), incidents (2), messaging (2)
```

`participation`, `safeguarding` and `sessions` form a cycle. They cannot be ported as three independent modules; see C3.

### External dependencies not yet in Symfony

| Needed contract | Owner (Java) | Used by | Symfony today |
| --- | --- | --- | --- |
| `PermissionChecker`, `AuthenticatedAccount` | IdentityAccess | all (about 110 uses) | **done** |
| `PersonDirectory`, `PersonEmailDirectory` | People | all (about 40 uses) | **done** |
| `MemberIdentity`, `MemberIdentityGuard` | IdentityAccess (V26) | sessions member views | missing |
| `AccountDirectory`, `ResponsibleAccountDirectory` | IdentityAccess | sessions, incidents, messaging | missing |
| `StaffingDirectory`, `StaffingUnavailable`, `ServingActivityLocks` | Volunteering (V14) | sessions staffing | missing |
| `ActivitySource` (implemented **by** Children, consumed by Volunteering) | Volunteering | sessions | missing |
| `EmailTransport`, `EmailTemplates`, `EmailDeliveries`, `DeliverySnapshot`, `DeliveryEligibility`, `TemplatePublicationPolicy` | Communications (V12, V13) | messaging only | missing |

Children Ministry does **not** depend on Events, Giving or Payments.

---

## 3. Target architecture in Symfony

### 3.1 Namespace layout

One bounded context, six modules, plus an internal shared kernel:

```
src/ChildrenMinistry/
  Shared/         {Domain, Application, Infrastructure}   ministry rules, failures, locking, audit, common mapping
  Participation/  {Domain, Application, Infrastructure, Presentation}
  Safeguarding/   {Domain, Application, Infrastructure, Presentation}
  Sessions/       {Domain, Application, Infrastructure, Presentation}
  Incidents/      {Domain, Application, Infrastructure, Presentation}
  Messaging/      {Domain, Application, Infrastructure, Presentation}
  Reporting/      {Application, Infrastructure, Presentation}
```

Namespace `App\ChildrenMinistry\<Module>\<Layer>\...`. Failures extend `Shared\Domain\ContextException` through
one `ChildrenException` in `ChildrenMinistry\Shared\Domain`. Audit uses `DbalAuditTable` with the existing children
audit table(s); check V3-V12 for the table names.

### 3.2 Tooling changes (done once, in session P0)

The tooling assumes `App\<Context>\<Layer>`. A nested module breaks three things:

| Tool | Change |
| --- | --- |
| `config/services.yaml` | Add excludes for `../src/ChildrenMinistry/*/Domain/*.php`, `.../Domain/ValueObject/`, `.../Application/Command/*/*Command.php`, `.../Application/Query/*/*Query.php` |
| `deptrac.yaml` | Layer regexes become `^App\\[A-Za-z]+\\(?:[A-Za-z]+\\)?(Domain\|Application\|Infrastructure\|Presentation)\\` so layer rules hold inside modules |
| `ContextBoundaryTest` | Context stays the first segment. Other contexts may import only `...\Application\Contract\...` at **any** depth. Add a second rule below |

Second rule (new test, `ModuleBoundaryTest`): inside `ChildrenMinistry`, a module may use another module's
`Domain` and `Application\Contract` namespaces and `Shared`, never another module's `Infrastructure`,
`Presentation` or command/query handlers. Admission and handover coordinate participation, safeguarding and
session rules in one transaction (Java `CONTEXT-MAP.md`), so Domain-level sharing is intended; handler-to-handler
calls are not.

### 3.3 Conventions that matter most for this context

- **Commands** go through `CommandBus` (one transaction each); **queries** call handlers directly and return read models.
- **Aggregates** have private constructors, `reconstitute`, and `Revision` for version and timestamps.
- **Lock order (must be preserved exactly):** child/person locks precede session locks; UUID ordering for multiple
  rows; live eligibility re-checked inside the transaction. Use `AdvisoryLock` and `findForUpdate`. Write a test per
  handler that takes more than one lock, asserting the order via a recording fake.
- **Safeguarding and incident payloads are restricted.** Reporting must never read support or incident payloads
  except through their explicit, audited use cases. Port that rule as a boundary test, not just as code.
- **Member self-service endpoints** (`listmychild*`, `getmychild`, pickup labels) use `MemberIdentity`; they are the
  last part of `sessions` and are gated on P1.
- Routes, status codes and JSON field names match Java exactly; the frontend and mobile app depend on them.

### 3.4 Parity verification

Java is the specification, so each session proves parity three ways:

1. **Port the Java tests** for the slice (`src/test/java/.../childrenministry/<module>`); they are the acceptance criteria.
2. **API tests** against the real schema (`IdentityApiTestCase` style) for every endpoint: success, 401/403, 404,
   409 stale version, 400 malformed, rollback on failure.
3. **Contract check:** diff the JSON of each new endpoint against the Java `PeopleApi`-style interface (field names,
   nulls, date formats) and against the Postman collection in `../postman` where one exists.

---

## 4. Status board

Legend: `[ ]` not started, `[~]` in progress, `[x]` done (make check green). Size: S under 1k Java lines, M 1-2k, L 2-3k.

### Prerequisites

| # | Session | Size | Needs | Status |
| --- | --- | --- | --- | --- |
| P0 | Tooling for nested modules (3.2), `ChildrenMinistry/Shared` skeleton, `ChildrenException` | S | - | [x] |
| P1a | IdentityAccess member identity: V26 tables, `MemberIdentity`, `MemberIdentityGuard`, `AccountDirectory`, `ResponsibleAccountDirectory` (contracts + adapters + tests) | M | - | [x] |
| P1b | People "my profile" handlers (`GetMyProfile`, `UpdateMyProfile`, `SaveMyContact`, `RemoveMyContact`) + `MemberProfileController` | S | P1a | [x] |
| P2a | Volunteering staffing slice, contracts: `StaffingDirectory`, `StaffingUnavailable`, `ServingActivityLocks`, `ActivitySource` (decision D1) | M | P0 | [x] |
| P2b | Volunteering staffing slice, adapters over V14 tables + tests | L | P2a, P1a | [x] |
| P3 | Communications email slice (needed only by messaging): `EmailTransport` over Symfony Mailer, `EmailTemplates`, `EmailDeliveries`, snapshots (V12, V13) | L | P0 | [x] |

### Children Ministry

| # | Session | Java source | Size | Needs | Status |
| --- | --- | --- | --- | --- | --- |
| C1 | Shared kernel + Participation core: programmes, classes, rooms, locations (no enrolment yet) | `shared`, `participation` (part) | M | P0 | [x] |
| C2 | Participation enrolment and placements, with safeguarding seams as contracts (see C3) | `participation` (rest) | M | C1 | [x] |
| C3a | Safeguarding domain: guardian authority, collector grants | `safeguarding` (part) | M | C1 | [x] |
| C3b | Safeguarding consent policies and evidence, readiness | `safeguarding` (part) | M | C3a | [x] |
| C3c | Safeguarding confidential support records (restricted payloads, audited reads) | `safeguarding` (rest) | M | C3b | [x] |
| C3d | Close the participation/safeguarding cycle: replace seam contracts with real cross-module calls; add `ModuleBoundaryTest` expectations | both | S | C2, C3c | [x] |
| C4 | Sessions A: session aggregate, create/list/get, status changes, `ActivitySource` implementation, locations | `sessions` (part) | L | C3d, P2a | [x] |
| C5 | Sessions B: staffing (`setsessionstaff`, serving candidates, workers register/review) | `sessions` (part) | L | C4, P2b | [x] |
| C6a | Sessions C: admission - `checkinchild`, expected children, rosters, station views | `sessions` (part) | L | C5 | [x] |
| C6b | Sessions C: handover - `checkoutchild`, `supervisorcheckout`, `transferchild`, pickup collectors, labels, attendance events/segments | `sessions` (part) | L | C6a | [x] |
| C6c | Sessions C: closeout and attendance queries | `sessions` (part) | M | C6b | [x] |
| C7a | Sessions D: recurring series (preview, create, cancel) - decide scheduler vs on-demand | `sessions` (part) | L | C4 | [x] |
| C7b | Sessions D: team planning (`previewteamplan`, `applyteamplan`, `listplanningworkers`), serving staff import | `sessions` (part) | M | C5, C7a | [x] |
| C8 | Sessions E: member family views (`listmychild*`, `getmychild`, `getfamilyarrivalreference`, `issuemychildpickuplabel`) | `sessions` (rest) | M | C6c, P1a | [x] |
| C9 | Incidents: restricted records, assignment, follow-up, closure, retention | `incidents` | M | C3c, C6c, P1a | [x] |
| C10 | Messaging: guardian eligibility, contact verification, message approval and queueing | `messaging` | L | C3d, P3 | [x] |
| C11 | Reporting: attendance reports and ministry overview, plus the restricted-payload boundary test | `reporting` | S | C6c, C9, C10 | [x] |
| C12 | Hardening: full parity sweep against Java tests and Postman, security review of safeguarding paths, README/AGENTS updates | all | M | all | [x] |

Rough total: 25 sessions (about 8 prerequisite, 17 context). Sessions in the same row group that do not share a
`Needs` can run in parallel sessions, but never two writers on the same files.

---

## 5. Session details

Each block lists what to read, deliver and prove. Add the slice inventory under each when you start it.

### P0 - Tooling
Deliver: `services.yaml` excludes, `deptrac.yaml` regexes, `ContextBoundaryTest` update, `ModuleBoundaryTest`, an empty
`ChildrenMinistry/Shared` with `ChildrenException`, and a trivial module file in each layer proving deptrac and the
service container accept nested modules. Exit: `make check` green with the new empty skeleton; AGENTS.md describes
the nested-module rule.

### P1a - Member identity
Read: `identityaccess/application/contract/{MemberIdentity,MemberIdentityGuard,AccountDirectory,ResponsibleAccountDirectory,
MemberLinkReader,PersonBindingPolicy}`, V26, V28, `MemberProfileApi`, and the member-related tests under `identityaccess`.
Deliver: contracts under `IdentityAccess/Application/Contract`, adapters, any member-link tables via V26 (already in
`migrations/`), and mobile device-session behaviour **only if** the member contracts require it; otherwise defer and log.
Prove: tests that a member can only act as their bound person, that `lockPerson` locks the person row, and that
`eligible(permission)` uses live permissions.

**P1a slice inventory (written at session start).**
- Tables (V26, already in `migrations/`): `identity_member_links` (account PK, person UNIQUE, active, version >= 1, verified_by/at), `identity_member_link_history` (immutable via `giving_reject_mutation`, UNIQUE account+version), `account_sessions.device_name` (already used by login). `payments_mobile_checkouts` is Payments, out of scope. V28's index/view belong to Children (C-sessions).
- Contracts (`IdentityAccess/Application/Contract`): `MemberIdentity`, `MemberIdentityGuard`, `AccountDirectory`, `AccountIdentityGuard`, `AccountNames`, `MemberAdmissionGuard`, `MemberLinkReader`, `PersonBindingPolicy`, `ResponsibleAccountDirectory`, `DeviceSessionReader`.
- Domain: `MemberLink` (version starts at 1; `revise` refuses a different person or a stale version), `MemberLinkRepository` (`lock`, `save(link, evidence)` with immutable history row).
- Application: `VerifiedMemberIdentity` (implements `MemberIdentity` + `MemberIdentityGuard`, uses People `PersonDirectory`), commands `VerifyMemberLink`, `RevokeMySession`, queries `ListMemberAccess`, `ListMySessions`.
- Adapters: `Published{AccountDirectory,AccountIdentityGuard,AccountNames,MemberAdmissionGuard,ResponsibleAccountDirectory}`, `DbalMemberLinkStore`, `DbalSessionStore::devices`.
- Endpoints: `GET /api/member-access`, `PUT /api/member-access/{accountId}` (both need `identity.manage` AND `people.manage`), `GET /api/me/sessions`, `DELETE /api/me/sessions/{sessionId}` (any authenticated account). All responses `Cache-Control: no-store`.
- Java tests to match: `VerifyMemberLinkHandlerSecurityTest`, `ListMemberAccessHandlerSecurityTest`, `RevokeMySessionHandlerSecurityTest`, `ListMySessionsHandlerSecurityTest`, `DeviceSessionsApiIntegrationTest`.

### P1b - People my-profile
Read the four Java `*MyProfile`/`*MyContact` handlers and `MemberProfileApiIntegrationTest`. Reuse `PersonRepository`
and `TitleReader`. Prove the `...SecurityTest` cases (a member cannot edit someone else's profile).

### P2a/P2b - Volunteering staffing slice (decision D1)
Port **only** what Children needs, but as the real Volunteering context's first slice so it is extended, not
replaced, later: `Volunteering/Application/Contract/{StaffingDirectory,StaffingUnavailable,ServingActivityLocks,ActivitySource}`
in P2a; the V14-backed adapters for `assess` and `candidates` in P2b (workers, credentials, team membership, approvals).
Read: `volunteering/application/contract/*`, the adapters implementing them, V14, V10. Prove readiness issues are
reported exactly as Java (`ready`, `issues[]`) for expired credentials, unapproved workers and conflicting duties.
Leave the rest of Volunteering (156 files) unported and listed in the log.

### P3 - Communications email slice
Port the contracts and a Symfony Mailer `EmailTransport`, template rendering with declared variables only (reject
missing or unknown variables, escape values, sanitize rich text), the delivery queue and snapshots. Keep Communications
free of Children dependencies. Defer push notifications, inbox and member notifications (V29, V30).

### C1 - Shared kernel and participation core
Read: `childrenministry/shared/*`, `participation` domain and application for programmes, classes, rooms, locations,
V3 (non-consent part). Deliver the ministry rules, locking helpers and common mapping, plus those aggregates with
full API tests. Anything in these handlers that calls safeguarding or sessions becomes a contract in
`Participation/Application/Contract` implemented later (record each seam in the log).

**C1 slice inventory (written at session start).**
- Tables: `children_programmes`, `children_classes`, `children_locations`, `children_rooms` (V3/V4), `children_child_guards`, `children_audit`. Permissions `children.read|manage|...` are seeded by V3.
- Java placement oddity: `CreateLocation`, `CreateRoom`, `ListLocations`, `ListRooms` live in the Java *participation* package but use the *sessions* repository and reader. **Decision:** Symfony `Location` and `Room` belong to the Participation module, so Sessions depends on Participation (as it already does) and not the other way round.
- Shared kernel (Java `childrenministry/shared`): `ChildrenRules`, `ChildrenException` (done in P0), `ChildLocks`, `ChildrenAudit`, `RequestFingerprint`, record mapping. `CalendarDate` moved from People to `Shared\Domain` because Children needs it too (with `InvalidInputException`, `plusYears`, `plusMonths`).
- Endpoints (all under `/api/children`): `POST /programmes`, `GET /programmes`, `GET /programmes/{programmeId}`, `POST /classes`, `GET /classes?programmeId=`, `POST /locations`, `GET /locations`, `POST /rooms`, `GET /rooms?locationId=`. Writes need `children.manage`, reads `children.read`.
- Left for later: consent policies (`PublishConsentPolicy`, `GetConsentPolicy`, `Programme.currentPolicyId` updates) -> C3b; enrolments, class assignments, roster -> C2.
- Java tests to match: `ChildrenRulesTest`, the programme/class/location/room parts of `ParticipationApiIntegrationTest` and `SessionApiIntegrationTest`.

**C2 seam list and inventory (written at session start).** Java participation imports from other modules:
| Java reference | Count | Resolution in Symfony |
| --- | --- | --- |
| `sessions.application.ParticipantAccess` (person lookup, availability, `lockChild`) | 5 | Pure service over `PersonDirectory` + `ChildLocks`; moved into `ChildrenMinistry/Shared/Application` (not a seam) |
| `sessions.application.contract.SessionReader`, `sessions.domain.repository.SessionRepository` | 7 | Locations/rooms only; already resolved by C1 (they live in Participation) |
| `safeguarding.domain.ConsentPolicy`, `PublishConsentPolicy*`, `GetConsentPolicy*` | 8 | Deferred to C3b (consent policy endpoints and `Programme.currentPolicyId` updates) |
| `safeguarding.application.ChildReadiness`, `ReadinessView(.Summary)` | 3 | **Seam:** contract `Safeguarding\Application\Contract\ChildReadiness::summaries(programmeId, childIds)`; a placeholder adapter returns no summaries (roster `readiness: null`) until C3 implements it; removed in C3d |

- Tables: `children_enrolments`, `children_class_assignments` (V3; both have `EXCLUDE ... daterange` overlap constraints, so overlaps surface as 409 through the shared listener), `children_child_guards`.
- Also moved to the app-wide `Shared` kernel: `ChurchCalendar` (Children also needs "today in the church zone").
- Handlers: `EnrolChild`, `AssignClass`, `EndEnrolment`, `EndClassAssignment`; queries `ListEnrolments`, `ListClassAssignments`, `ListProgrammeChildren` (roster).
- Endpoints (`/api/children`): `POST /enrolments`, `POST /class-assignments`, `POST /enrolments/end`, `POST /class-assignments/end`, `GET /enrolments?childId=`, `GET /class-assignments?childId=`, `GET /programmes/{programmeId}/children?search=&status=&limit=25&offset=`. Writes need `children.manage`, reads `children.read`.
- Lock order in every write: child's person row and guard row first (`ParticipantAccess::lockChild`), then the enrolment/assignment row. Handlers that start from an enrolment id read it unlocked to learn the child, lock the child, then re-read `FOR UPDATE`.
- Java tests to match: enrolment/placement/ended-enrolment/re-enrolment cases of `ParticipationApiIntegrationTest`; `ProgrammeRosterApiIntegrationTest`; `ListProgrammeChildrenHandlerIntegrationTest`.

### C2 / C3a-d - Participation enrolment and safeguarding
Start C2 by listing the 13 participation-to-safeguarding and 12 participation-to-sessions references. For each,
decide: shared Domain type (allowed) or contract (preferred). C3a-c port safeguarding in dependency order
(authority, then consent, then restricted support). C3d removes the seams. The restricted-payload rules (who may
read support records, audit on every read) get dedicated tests.

### C4 - Sessions A
Read: `sessions/domain` session aggregate and status rules, `createsession`, `setsessionstatus`, `getsession`,
`listsessions`, `getsessionday`, `liststationsessions`, `ChildrenSessionsConfiguration`, `ActivitySource` adapter, V4.
Deliver the session lifecycle (scheduled, cancelled, rescheduled), the published `ActivitySource` implementation, and
the rule that cancelling closes arrivals but preserves open custody.

### C5 - Staffing and workers
Read: `setsessionstaff`, `listservingcandidates`, `registerworker`, `reviewworker`, `getworker`, `importservingstaff`,
`ServingStaffConfiguration`, V4, V10. Staffing eligibility comes from `StaffingDirectory`; Children owns only its opaque
assignment links. A session cannot lose supervising staff while children remain checked in.

### C6a/C6b/C6c - Admission, handover, closeout (the risky core)
Read the handlers listed in the board plus domain policies for age eligibility, capacity, the 30-minute arrival window,
single open check-in per child, authorised collectors, and V5/V6. This is where lock ordering lives: write the
ordered-lock tests first. Port Java tests `checkinchild`, `checkoutchild`, `transferchild` and handover first and
treat them as the definition of done. Concurrency test: two simultaneous check-ins of the same child, exactly one wins.

### C7a/C7b - Series and team planning
First step: read how Java generates occurrences (V9) and decide between on-demand generation (preferred, no
scheduler) and a Symfony Scheduler task. Record the choice in the log. Team planning previews are pure functions of
series, workers and rules: port them as domain services with table-driven tests.

### C8 - Member family views
Gated on P1a. Each endpoint resolves the person through `MemberIdentity`, then authorises via guardian authority from
safeguarding; the person id is never an entitlement. Port `MemberFamily` configuration and V28 index usage.

### C9 - Incidents
Restricted records: every read and write is audited and permission-checked individually. Port assignment via
`ResponsibleAccountDirectory`, retention rules, and the closure workflow with the Java tests.

### C10 - Messaging
Children owns the *meaning* of guardian messages; Communications only delivers. Port eligibility, contact verification
and approval. The queueing adapter revalidates recipient authority, contact verification, consent policy and the
approver's live permissions immediately before handing a snapshot to `EmailDeliveries`.

### C11 - Reporting
Read-only. Compose module read contracts; add `ReportingBoundaryTest` asserting reporting code imports no support
or incident payload types, only their audited use-case contracts.

### C12 - Hardening
Run every ported Java test mapping (keep a table in the log: Java test class to PHP test class). Sweep for lock-order
gaps, unaudited reads of restricted data, and status/JSON drift. Update README and AGENTS.md.

---

## 6. Risks and mitigations

| Risk | Mitigation |
| --- | --- |
| Participation, safeguarding and sessions form a cycle | Seam contracts in C2/C3, removed in C3d; never port the three side by side without a seam list |
| Lock ordering regressions in admission and handover | Ordered-lock tests written first; recording fakes; concurrency tests in C6 |
| Restricted safeguarding or incident data leaks through reporting or logs | Dedicated boundary tests; read models without payloads; audit every read; no payloads in exception messages |
| Prerequisite scope creep (Volunteering is 5.4k lines, Communications 3.3k) | Port slices behind contracts only (D1); list everything deferred in the log |
| Frontend/mobile break on JSON drift | Contract check against Java and Postman in every session |
| Sessions too large to finish | Stop at green, split the row, record "Next session notes" |
| Tooling assumes one namespace level | P0 first; nothing else starts before it |

---

## 7. Definition of done (per session and per module)

- `make check` green (cs, stan level 8, deptrac, phpunit), no baseline or ignore additions.
- Every Java handler in the slice has a PHP command/query handler, a route, and an API test; every ported Java test has a PHP equivalent or a logged reason.
- Aggregates: private constructors, `reconstitute`, `Revision` where Java has a version. Queries return read models.
- Permissions: `PermissionChecker` in handlers, `#[IsGranted]` on routes. Restricted data audited.
- AGENTS.md updated if a new rule was learned. Board and log updated.

## 8. Decisions

| # | Question | Decision |
| --- | --- | --- |
| D1 | How much of Volunteering to port before sessions staffing | **Decided:** contracts + staffing readiness slice only (P2a/P2b). The rest of Volunteering stays unported and is listed in the log. |
| D2 | Module layout | **Decided:** nested modules in one `ChildrenMinistry` context (3.1). |
| D3 | Member self-service in this effort | **Decided: include** (P1a, P1b, C8). The mobile app already calls `/api/member/children` and `/api/member/children/{id}` (`mobile/src/app/children.tsx`, `mobile/src/app/child/[id].tsx`, `mobile/e2e/member-app.spec.ts`), so these endpoints are needed. |
| D4 | Recurring series generation | **Decided in C7a: on demand, no scheduler.** Java materialises every included session of a series (at most 52, within 366 days) inside the create transaction, then links them in `children_series_sessions`. Nothing generates sessions later, so there is nothing for a scheduler to do; the Symfony port does the same. |
| D5 | Email in P3 | **Decided:** Symfony Mailer directly; async transport is a later hardening step. |

## 9. Decision log and session notes

_Append newest first. Format: `YYYY-MM-DD - session - decision / deviation / seam / Next session notes`._

- 2026-11-07 - C12 - Done. Hardening sweep; cs, phpstan, deptrac and the full suite green on a private database.
  - **Added:** `ReportingBoundaryTest` (Reporting imports no incident/support/consent/authority/pickup type and reaches Incidents and Safeguarding only through `Application\Contract`; its SQL names no restricted table or secret column), the transfer-across-programmes journey test (`ReportingApiTest::testTransfersRemainInReportsAndJourneyAcrossProgrammeAndDateFilters`) and the three Java `PickupApiIntegrationTest` scenarios that had no PHP equivalent (`AttendanceApiTest`: revoked grant blocks handover even for a supervisor; revoked guardian authority ends admission and an unrelated adult cannot collect; collector needs a known adult birth date). README and AGENTS.md updated (contexts, email worker, restricted-data rule, lock-order rule).
  - **Sweep findings (no code change needed):** every command handler either audits in `handle()` or delegates to one that does (`CheckOutChild`/`SupervisorCheckout` -> `Handover` audits `attendance.*` and rejected labels; `ImportServingStaff` -> `SetSessionStaff`; delivery cancel/retry/receipt -> Communications' `communication_audit`). Every handler that changes participant state locks through `ParticipantAccess::lockChild` / `ChildLocks`, the incident, communication or scheduling lock; the orders are asserted in the handler tests listed in C7-C10. Restricted reads are audited (`AccessIncident`, `AccessChildSupport`); lists, reports and overview carry no payloads. Guardian authority/consent/pickup evidence references are returned to `children.safeguarding` holders exactly as Java does (parity, not a leak).
  - **Java-to-PHP test map** (all 78 Java test classes under `childrenministry`; handler integration tests are folded into the API test of their module):
    | Java | PHP |
    | --- | --- |
    | `shared/ChildrenRulesTest` | `Shared/ChildrenRulesTest`, `Participation/Domain/ParticipationDomainTest` |
    | `participation/*` (3 classes) | `Participation/Presentation/{ParticipationApiTest,ProgrammeRosterApiTest,EnrolmentApiTest}` |
    | `safeguarding/ChildReadiness*`, `SafeguardingFollowUp*` (4) | `ConsentAndReadinessApiTest`, `SafeguardingFollowUpApiTest` |
    | `safeguarding/SupportApiIntegrationTest` | `SupportApiTest`, `SupportHandlersTest`, `SupportRecordTest`, `RestrictedPayloadBoundaryTest` |
    | `sessions/domain/{Attendance,CapacityPolicy,WeeklyRecurrence}Test` | `Sessions/Domain/{AttendanceTest,CapacityAndWorkerTest,WeeklyRecurrenceTest}` |
    | `sessions/presentation/{Session,SessionDay,Station,SessionCloseout}ApiIntegrationTest` | `SessionApiTest`, `AttendanceApiTest` (station, expected view, closeout cases) |
    | `sessions/presentation/{Attendance,Pickup}ApiIntegrationTest` | `AttendanceApiTest`, `HandoverHandlersTest`, `LabelAndTransferHandlersTest` |
    | `sessions/presentation/ServingStaffApiIntegrationTest` | `SessionStaffingApiTest`, `StaffingHandlersTest` |
    | `sessions/presentation/{SessionSeries,TeamPlanning}ApiIntegrationTest` + their handler tests | `SessionSeriesApiTest`, `SeriesHandlersTest`, `TeamPlanningApiTest`, `TeamPlanningTest` |
    | `sessions/*SecurityTest` (member family), `MemberFamilyApiIntegrationTest` | `MemberFamilyApiTest`, `MemberFamilyLabelTest` |
    | `sessions/presentation/{Children,Family}DocumentationIntegrationTest` | not ported (OpenAPI documentation checks) |
    | `incidents/*` (10) | `IncidentTest`, `IncidentHandlersTest`, `IncidentStoreTest`, `IncidentApiTest` |
    | `messaging/*` (17) | `GuardianCommunicationTest`, `MessagingApiTest` |
    | `reporting/*` (7) | `AttendanceReportingTest`, `AttendanceReportCsvTest`, `ReportingApiTest`, `ReportingBoundaryTest` |
  - Not done, by decision: the Postman collection was not re-run against Symfony (no live Java/Symfony pair in this session); JSON field names follow the Java records and are asserted field by field in the API tests. Sending email stays off until `COMMUNICATIONS_EMAIL_ENABLED=true`, a sender and a running scheduler worker exist.
- 2026-11-06 - P3 - Done. cs, phpstan level 8 and deptrac (0 violations) clean; 114 new tests; the whole suite (851 tests at that point) passed on a private database.
  - New context `src/Communications` (Domain, Application, Infrastructure, Presentation), no migrations (V12/V13 already applied). New dependency: `symfony/html-sanitizer` (composer.json/lock changed; there was no HTML sanitizer in the project).
  - **Published contracts** (`Communications/Application/Contract`): `EmailDeliveries` (`queue`, `cancel`, `retry`, `receipt`), `EmailTemplates` (`render` -> `RenderedEmail`), `EmailTransport` (+ `TransportConfiguration`, `TransportResult`), `DeliveryEligibility`, `TemplatePublicationPolicy`, `DeliveryReader` (+ `DeliverySnapshot`, `DeliverySummary`, `DeliveryPage`, `DeliveryEvent`, `DeliveryReceipt`), `RichText`, `DeliverySources`, `CommunicationAudit`, `TemplateReader` (+ views). `MemberNotifications`, push and inbox contracts are not ported (V29, V30).
  - **Registration by tag, as for `ActivitySource`:** a context implements `DeliveryEligibility` (its `sourceContext()` names it, for example `children-ministry`) and `TemplatePublicationPolicy`; `services.yaml` tags both and injects them into `DispatchEligibility` and `PublishTemplateHandler`. Exactly one policy must match a delivery's source or the delivery is refused. `CommunicationsIndependenceTest` fails the build if Communications imports ChildrenMinistry, Volunteering or People, or names a Children concept in code.
  - Delivery lifecycle as Java: `queue` inserts, records the source, asks the policy to authorize then to confirm eligibility, audits, adds an event, all in the caller's transaction. `cancel` only for QUEUED/SCHEDULED/FAILED/BLOCKED; `retry` only for FAILED/BLOCKED, at most 3 attempts, re-approved by the retrying actor and re-checked; `receipt` is idempotent per provider event key (a reused key with different content is 409) and only settles a live SENT/UNKNOWN delivery whose Message-ID matches.
  - Dispatch: `DeliveryProcessor` claims in one transaction (row lock, eligibility re-check, `BLOCKED` with `ELIGIBILITY_CHANGED` if the source says no), sends with **no transaction open** (asserted), then records the outcome in another, dropping it if another attempt now owns the row. Anything the transport throws is `UNKNOWN`/`TRANSPORT_UNCERTAIN`, never `FAILED`, so nothing is sent twice without a person deciding. Stale `SENDING` rows (older than 5 minutes) become `UNKNOWN` via `FOR UPDATE SKIP LOCKED`. At most 25 per run. A two-connection test proves a second worker blocks on the row.
  - **Mail:** `MailerEmailTransport` over Symfony Mailer (D5). One recipient, UTF-8, plain or HTML, stable `Message-ID` (verified against a real Mailpit: sent, one recipient, correct subject/body/id). Capture mode refuses any host except `localhost`, `127.0.0.1`, `::1`, `mailpit`. Invalid address is `FAILED/INVALID_EMAIL`; an authentication error is `FAILED/SMTP_AUTHENTICATION`; any other transport error is `UNKNOWN/SMTP_UNCERTAIN`. New env: `COMMUNICATIONS_EMAIL_ENABLED` (default false), `COMMUNICATIONS_EMAIL_CAPTURE`, `COMMUNICATIONS_EMAIL_FROM` (Java: `onechurch.email.*`).
  - **Trigger (replaces Java's `@Scheduled`):** console command `app:communications:dispatch-due`, run every 15 seconds by `Schedule.php` (`RecurringMessage` + `RunCommandMessage`). Production needs a worker: `php bin/console messenger:consume scheduler_default` (verified: it handled the message). Nothing runs without that worker; the command can also be run by hand.
  - Templates: `TemplateContent` (declared variables must equal the ones used; names `[a-zA-Z][a-zA-Z0-9_]{0,49}`; at most 30; values at most 5000 characters; values inserted as literal text, HTML-escaped in the body, plain in the subject, never re-expanded; rendered subject one line of at most 200 characters; rendered body at most 200000), `EmailTemplate` lifecycle (DRAFT -> PUBLISHED with an immutable revision per publication; editing a published template makes a draft while the published revision stays in use; ARCHIVED stops rendering), `SafeEmailHtml` (allows p, br, strong, em, u, s, ul, ol, li, h2, h3, blockquote, a; links only https, http, mailto, always `rel="noopener noreferrer"`; no scripts, styles, images, forms, event handlers; a variable inside any attribute is refused). Routes under `/api/communications/templates`: `GET` list/one/revisions and `POST /preview` (`communications.templates.read`); `POST` create, `PUT /{id}`, `POST /{id}/publish`, `POST /{id}/archive` (`communications.templates.manage`; manage implies read). Create, update, publish and archive each write a `communication_audit` row.
  - Deviations: ids are strings and instants `DateTimeImmutable`; `expectedVersion` is required (missing is 400); the sanitizer is Symfony's, so output differs from Jsoup in harmless ways (the `@` in `mailto:` links is entity-encoded, an anchor whose address was removed keeps its `rel`); `DeliveryFingerprint` still counts UTF-16 code units so receipt hashes written by Java stay comparable; `TemplateController` returns the same JSON field names as Java (`templates`/`total`, revision and view fields).
  - Not ported: push devices and settings, the member inbox, `MemberNotifications`, the Java list/inspect delivery endpoints (those live in the Children messaging package and arrive with C10).
  - Java tests mapped: `TemplateContentTest` -> `TemplateContentTest` (plus table-driven invalid cases); `SafeEmailHtmlTest` -> `SafeEmailHtmlTest`; `SmtpEmailTransportTest` -> `MailerEmailTransportTest` (a fake mailer instead of a socket server, plus the Mailpit smoke run above); `TemplateApiIntegrationTest` and the eight template handler integration tests -> `TemplateApiTest` and `PublishTemplateHandlerTest`.
  - Next session notes: C10 implements `DeliveryEligibility` (source `children-ministry`) and a `TemplatePublicationPolicy` that keeps the `message` variable on `children.*` templates, builds a `DeliverySnapshot` (status `QUEUED`, attempts 0, version 0, a unique `<uuid@onechurch.delivery>` message id, review hash from `DeliveryFingerprint`) and calls `EmailDeliveries::queue('children-ministry', ...)` inside its own command transaction. Render with `EmailTemplates::render('children.session-reminder' | 'children.consent-renewal', ['message' => ...])`; both are seeded and published. `children_guardian_communications` and `children_verified_emails` (V11/V12) are Children's own tables. Sending stays off until `COMMUNICATIONS_EMAIL_ENABLED=true` and a sender are configured. Use a private test database (see the C7 note) while another session may be running tests.
- 2026-11-07 - C10/C11 - Done together. cs, phpstan level 8 and deptrac (0 violations) green; 909 tests pass on a private database.
  - **C10, `ChildrenMinistry/Messaging`** (Domain `GuardianCommunication`, `VerifiedGuardianEmail`, enums, two repositories; Application `GuardianCommunicationPolicy`, `DeliveryPolicy`, commands Create/RecordContact/VerifyEmail/RevokeEmail/QueueEmail/CancelDelivery/RetryDelivery/RecordReceipt, queries List/Get communication, GetEmailReview, List/Get deliveries; Infrastructure `DbalGuardianCommunicationStore`, `DbalVerifiedEmailStore`, `GuardianDeliveryEligibility`, `GuardianTemplatePolicy`). Routes under `/api/children`: `guardian-communications` (POST 201, GET, GET `/{id}`, POST `/{id}/contact` 204, GET `/{id}/email-review`, POST `/{id}/verify-email` 201, POST `/{id}/queue-email` 201), `verified-emails/{id}/revoke`, `email-deliveries` (GET, GET `/{id}`, POST `/{id}/cancel`, `/{id}/retry`, `/receipts`). Everything needs `children.safeguarding`; queue, cancel and retry also `children.communications.send`; receipts `children.communications.receipts`.
  - Rules as Java: approval binds a fingerprint of the exact wording, recipient, contact and communication version; one delivery per communication; schedule within 90 days (60 s grace in the past); verification at most a year and must cover the send time; the dispatcher (`GuardianDeliveryEligibility`) re-checks the approver's live permissions, the guardian, the contact, the verification and the fingerprint before every send; manual contact is refused while a non-cancelled delivery exists; cancel, retry and receipts only touch `children-ministry` deliveries (404 otherwise). Nothing is queued while email is not configured (409).
  - Boundary fix: the policy returns the guardian id and a local `ProgrammeFacts` instead of naming other modules' `*View` classes (the module boundary test caught it).
  - **Safeguarding follow-up ported here** (the Java overview depends on it, and C7 had left it unported): `GET /api/children/safeguarding-follow-up` (`SafeguardingFollowUp`, `DbalFollowUpStore` with a REPEATABLE READ snapshot via the new `Shared\Infrastructure\Persistence\ConsistentRead`, views in `Safeguarding\Application\Contract`). Dates and states only; care review dates need `children.support`, and `kind=CARE` is 403 without it. `ChildReadiness` gained `details()` (C8).
  - **C11, `ChildrenMinistry/Reporting`** (Application only plus Infrastructure and Presentation): `GET /api/children/attendance-reports` (`children.read`), `.../export` (CSV, `children.read` and `children.manage`, 10,000-visit cap, formula-safe cells, BOM), `GET /api/children/attendance/{id}/timeline`, `GET /api/children/overview`. Dates are church-local and inclusive, at most 366 days; totals cover the whole filtered result before paging.
  - **Overview** reaches other modules only through contracts: Sessions `TeamPlanningAttention` (view classes `TeamPlanningView`, `PlanningSummary`, `PlanningSessionView`, `PlanningStaffView` moved into `Sessions\Application\Contract`), Safeguarding `SafeguardingFollowUps`, Incidents `ActiveIncidents` (`IncidentSummary`/`IncidentPage` moved to `Incidents\Application\Contract`), Communications `DeliveryReader`. A section the caller may not see is `null`, an oversized cohort becomes a notice. Because a module may not use another's Presentation classes, `Reporting\Presentation\OverviewSections` writes those embedded sections out again; `ReportingApiTest::testTheOverviewSectionsMatchTheirOwnEndpoints` guards the incidents, follow-up and deliveries sections against drift (the team section is covered by field-for-field construction, not a parity test).
  - **Restricted-payload rule as a test:** the overview and reports never contain incident descriptions or actions, care text, pickup codes, evidence or collector identity (asserted in `ReportingApiTest`). Reporting imports no `IncidentReader` details/events and no support types; `ModuleBoundaryTest` and deptrac enforce the module walls. A dedicated `ReportingBoundaryTest` naming the forbidden types is still a C12 item.
  - Deviations: Java's `GetChildAttendance` handler sat in its reporting package but served `GET /api/children/attendance/children/{childId}` from the attendance controller; it now lives in Sessions (`GetChildAttendanceHandler`, `AttendanceReader::childAttendance`). Review hashes use `RequestFingerprint`, so Java-created approvals would not match (same caveat as C7/C9). Test env: `config/services.yaml` gained a `when@test` block that replaces the mail transport with `tests/Support/RecordingEmailTransport` (disabled until a test enables it) and makes `DeliveryProcessor` public; API tests that use it call `$client->disableReboot()`.
  - Java tests mapped: `GuardianCommunicationApiIntegrationTest` and `EmailDeliveryApiIntegrationTest` -> `MessagingApiTest` (the concurrent-dispatch test is the sequential double dispatch plus Communications' own row-lock test; the template-revision test is covered by Communications' template tests and the fingerprint check); the messaging handler integration tests -> the same API tests; `SafeguardingFollowUpApiIntegrationTest` -> `SafeguardingFollowUpApiTest`; `AttendanceReportingTest`, `AttendanceReportCsvTest` -> `AttendanceReportingTest`, `AttendanceReportCsvTest`; `AttendanceReportingApiIntegrationTest`, `MinistryOverviewApiIntegrationTest` -> `ReportingApiTest` (the transfer-across-programmes journey scenario is not ported separately).
  - Next session notes: C12 (hardening): build the Java-to-PHP test mapping table, add `ReportingBoundaryTest`, sweep lock-order and audit gaps, port the transfer-journey report scenario, and update README/AGENTS.md. Sending stays off until `COMMUNICATIONS_EMAIL_ENABLED=true`, a sender and a running scheduler worker exist.
- 2026-11-06 - C9 - Done. cs, deptrac (0 violations) and the 843-test suite green on a private database; phpstan reports only errors in the Communications (P3) session's tests, none in Incidents.
  - New module `src/ChildrenMinistry/Incidents`: Domain `Incident` (private constructor, `report`/`reconstitute`/`triage`/`progress`), `IncidentDetails` and `IncidentEvent` (redacted in `print_r`/`var_dump`), enums for category, severity, status and event type, `IncidentRepository`; Application `IncidentAccess`, read models (`IncidentSummary` has metadata only), `IncidentReader`, commands `ReportIncident`/`AccessIncident`/`FollowUpIncident`/`PurgeExpiredIncidents`, queries `ListIncidents`/`ListIncidentAttendance`/`ListIncidentGuardians`/`ListIncidentOwners`; `DbalIncidentStore`; `IncidentController`, `IncidentJson`.
  - Routes under `/api/children/incidents`: `POST ''` (201 `{id}`; `.record` with a place on the session, or `.manage`), `GET ''` (metadata, `.manage`), `POST /{id}/access` (200, audited, `no-store`), `POST /{id}/follow-up` (204), `GET /attendance` (`.record` with scope, or `.manage`), `GET /{id}/guardians`, `GET /owners`, `POST /purge-expired` (`{purged}`).
  - Restricted-data rules, all tested: private details are only returned by `AccessIncident`, which audits every successful call (each page of history); the ordinary audit rows for incidents carry no description or note; list/guardian/attendance reads return identifiers, names and dates only; retention (`retain_until`) hides an incident from every read and follow-up, and only the explicit bounded `purge-expired` call deletes details and blanks event notes (reads never purge); request ids are actor-scoped, identical retries return the same incident and a changed payload is a 409 (the hash is a `RequestFingerprint`, so Java-created rows are not replay-compatible, as in C7).
  - Lock order: report = child person row, then the session, then scope; follow-up = incident row, then (guardian contact) the guardian authority row; both asserted in `IncidentHandlersTest`, and `IncidentStoreTest` proves a second connection waits on the incident row (55P03). Updates are version-guarded in the WHERE clause (Java relied on the lock alone).
  - Seams closed: the Sessions closeout/close gates now read counts through the published `Incidents\Application\Contract\SessionIncidentCounts` (`DbalSessionIncidents` was replaced by `PublishedSessionIncidents`, which delegates). Incidents needs session scope, so it owns `Contract\IncidentSessionScope`, implemented in Sessions by `IncidentSessionAccess` over `StaffScope`. Incidents uses Sessions' `SessionRepository` (Domain) and Safeguarding's `SafeguardingReader` (Contract) and `SafeguardingRepository` (Domain) only; `ModuleBoundaryTest` passes.
  - Deviations: the report route has no `#[IsGranted]` because either permission suffices and the project has no expression-language component; the handler enforces both cases and tests prove 403 for readers and for staff with neither permission. After a purge, blank event notes are shown as "(removed after retention)" if ever read (Java would have thrown; unreachable through the API because purged incidents are past retention). `ListIncidentGuardians` and `ListIncidentOwners` return the Java field names (`authorityId`/`guardianId`/`name`, `id`/`name`).
  - Java tests mapped: `IncidentTest` -> `IncidentTest`; `IncidentApiIntegrationTest` -> `IncidentApiTest` (retry safety and audit of each page, lifecycle with five history events, stale follow-up, independent reporting vs private permissions, invalid times/owners, closeout acknowledgement, retention; the concurrent-follow-up test is the sequential stale-version case plus the row-lock test); the eight `*HandlerIntegrationTest` classes -> `IncidentHandlersTest` and the list/attendance/guardian/owner API tests. Not ported: the `/attendance-reports` privacy assertion (reporting is C11, which adds the boundary test that reporting never imports incident payload types).
  - Next session notes: C10 (messaging) needs P3 (email slice, in progress elsewhere). C11 needs C10 and adds `ReportingBoundaryTest`; Reporting must use only `SessionIncidentCounts`-style counts, never `IncidentReader::details/events`. Run PHPUnit on a private database while other sessions are testing.
- 2026-11-06 - C8 - Done. cs, phpstan level 8 and deptrac (0 violations) green; 695 tests pass (25 new) on a private database.
  - Delivered under `Sessions`: `MemberFamilyViews` (guardian, require, children, programmes, sessions, attendance, canCollect), read models (`FamilyChild`, `FamilyPage<T>`, `FamilyProgramme`, `FamilySession`, `FamilyVisit`, `FamilyAttendance`, `FamilyEnrolment`, `FamilySessionRow`, `FamilyArrivalReview`), `MemberFamilyReader` + `DbalMemberFamilyStore` (uses the V28 index and the `children_member_session_dates` view), queries `ListMyChildren`/`GetMyChild`/`ListMyChildProgrammes`/`ListMyChildSessions`/`ListMyChildAttendance`/`GetFamilyArrivalReference`, command `IssueMyChildPickupLabel`, `MemberFamilyController`, `FamilyArrivalReferenceController`, `MemberFamilyJson`.
  - Routes (no staff permission; `Cache-Control: no-store`): `GET /api/member/children` (an unverified account gets `verified:false` and an empty page, as in Java), `GET /api/member/children/{childId}`, `.../programmes`, `.../sessions`, `.../attendance`, `POST .../attendance/{attendanceId}/pickup-label` (200 `LabelIssue`; `replacePrevious` must be true, otherwise 400); staff: `GET /api/children/sessions/{sessionId}/arrival-reference/{childId}` (`children.station` + `StaffScope`, reads only). JSON matches Java and the mobile models (`FamilyPage`, `FamilyVisit.visit`, `FamilyLabel`). `status` is the Java enum name (`PLANNED`, `NEEDS_REVIEW`...), i.e. the enum value.
  - Authority rules: every call resolves the caller's verified person through `MemberIdentity::requirePerson`, then requires an unrevoked guardian authority that spans now; a child outside it is 404, never 403. People/household relationships grant nothing (tested with a `PARENT_OF` relationship). The guardian must be a recorded adult (400 otherwise). Attendance history starts at the earliest current authority for that child. The label is five minutes at most and never outlives the guardian's own authority, the own pickup grant, or that grant's authority; the code is stored hashed. Lock order: member link, then people in id order, then the child guard (`ParticipantAccess::lockChild`), then the session, then the visit.
  - Cross-module seam: `ChildReadiness` (Safeguarding contract) gained `details()` returning `ReadinessDetail` (summary, `ReadinessStepStatus` list, time zone) so Sessions can show step codes without importing Safeguarding's Application internals. `ReadinessAssessor` implements it from the existing `ReadinessView`.
  - Deviations: queries carry the caller's `actorId` (Java injected `AuthenticatedAccount` into the handler); a child that cannot be assessed for readiness yields empty steps and the church time zone instead of a Java `NPE`.
  - Java tests mapped: `MemberFamilyApiIntegrationTest` -> `MemberFamilyApiTest` (authority/expiry/revocation, parent relationship, programme and session snapshots, midnight local date, attendance minimisation, label rules, revoked pickup rights, staff arrival-reference review; the "staff must recheck authority after a member label" scenario is covered by the existing `AttendanceApiTest` checkout tests because the member label uses the same hash/expiry contract as staff labels) plus `MemberFamilyLabelTest` (lock order, five-minute cap, authority caps, refusals). Not ported: `FamilyDocumentationIntegrationTest` (OpenAPI documentation checks).
  - Next session notes: C9 (incidents) needs C3c, C6c, P1a: all done. C10 needs P3 (email). C11 needs C9 and C10. Run PHPUnit against a private database when another session may be testing (see the C7 note).
- 2026-11-06 - C7a/C7b - Done together. cs, phpstan level 8 and deptrac (0 violations) green; 670 tests pass (about 100 new for C7) when run against a private database (see Next session notes).
  - **D4 decided: on-demand, no scheduler.** Java creates every included session of a series in the create transaction (at most 52 over 366 days) and links them in `children_series_sessions`; nothing is generated later. The port does the same, so there is no Symfony Scheduler task.
  - C7a delivered under `Sessions`: Domain `WeeklyRecurrence` (+ `RecurrenceSlot`), `SessionSeries` (aggregate with `create`/`reconstitute`/`stop`), `SessionSeriesRepository`; Application `SessionSeriesDraft` (idempotency fingerprint via `RequestFingerprint`), `SessionSeriesPlanning` (the preview), read models (`SeriesPreview`, `SeriesPage`, `SeriesDetail`, ...), `SessionSeriesReader`, commands `CreateSessionSeries`/`CancelSessionSeries`, queries `PreviewSessionSeries`/`ListSessionSeries`/`GetSessionSeries`; `DbalSessionSeriesStore`; `SessionSeriesController`. Routes: `POST /api/children/session-series/preview` (200), `POST /api/children/session-series` (201 `{id}`), `GET /api/children/session-series`, `GET /api/children/session-series/{id}`, `POST /api/children/session-series/{id}/cancel` (204). Preview/create/cancel need `children.manage`, reads `children.read`.
  - C7b delivered: `TeamPlanning` service (list, workers, preview, validate), `TeamPlanningReader` + `TeamAssignmentRepository` (new Domain repository for the one delete), `DbalTeamPlanningStore`, command `ApplyTeamPlan`, queries `GetTeamPlanning`/`PreviewTeamPlan`/`ListPlanningWorkers`, `TeamPlanningController`. Routes: `GET /api/children/team-planning`, `GET .../workers`, `POST .../preview` (200), `POST .../apply` (204), all `children.manage`. `importservingstaff` was already ported in C5 and is not repeated. `GET /api/children/safeguarding-follow-up` lives in `CoordinatorPlanningApi` in Java but belongs to Safeguarding; it is not part of C7 (see Not ported).
  - Lock order (tested with recording doubles and real two-connection tests): create = scheduling guard row, then the source session, then the preview and inserts; cancel = scheduling guard, series row, then the series' sessions in UUID order (byte order); apply team plan = selected sessions in UUID order, then the workers in UUID order, then the preview is recomputed under those locks, so two plans for the same worker serialise and the second sees the clash (Java's concurrency test, here proved by the row lock plus the blocker test).
  - Rules ported in Java's order and wording: recurrence bounds (1-52 dates, every 1-4 weeks, within 365 days of span, same-day end after start, whole minutes), exclusions must belong to the pattern and leave one session, gap/overlap wall times are refused with "ambiguous or unavailable", creation re-checks future starts and class/room clashes under the guard and returns the existing series for an identical retry (409 for the same request id with a changed plan), copied teams are absent, cancellation only touches future PLANNED sessions and refuses while children are present, team plans cap at 25 distinct sessions, never rewrite confirmed presence, and block on overlapping bookings (adjacent and closed/cancelled sessions do not clash).
  - Deviations: request ids are scoped to the actor exactly as in Java, but the stored `request_fingerprint` is a `RequestFingerprint` SHA-256, not Java's pipe-joined string, so a retry of a Java-created request against Symfony would read as "different schedule" (no production overlap expected). Times in a recurrence are accepted as `HH:MM` or `HH:MM:00`; Jackson also accepts fractional seconds, which Java then rejects for non-zero values, so both give 400. `DbalSessionStore::session()` is now public and `workerFrom()` was extracted so the planning store reuses the row mappers. Request DTO `targets` is validated with `Assert\Collection` because the serializer does not hydrate items from a phpdoc list type.
  - Not ported / owned elsewhere: `GET /api/children/safeguarding-follow-up` (Safeguarding, C3 follow-up if wanted; the Java handler is `GetSafeguardingFollowUpHandler`).
  - Java tests mapped: `WeeklyRecurrenceTest` -> `WeeklyRecurrenceTest` (plus a bounds table); `SessionSeriesApiIntegrationTest` -> `SessionSeriesApiTest` (the concurrent-retry test is covered by `SessionSeriesStoreTest::testTheSchedulingGuardBlocksAnotherConnectionUntilTheHolderFinishes` and the idempotency handler test, since a single-process WebTestCase cannot run two requests at once); `*SessionSeries*HandlerIntegrationTest` -> `SeriesHandlersTest`; `TeamPlanningApiIntegrationTest` -> `TeamPlanningApiTest` (concurrent apply -> `TeamPlanningStoreTest::testTheWorkerRowLockSerialisesConcurrentPlansForTheSameWorker` and `TeamPlanningTest::testApplyLocksSessionsThenWorkersInUuidOrder...`).
  - Next session notes: C8 (member family views) is the only unblocked Children session left on the board besides C9/C10 after P3. **Test database collisions:** `phpunit.dist.xml` forces one database, and `DatabaseTestCase` drops and rebuilds the schema once per run, so two PHPUnit runs at the same time (two sessions, or `make check` while another run is going) fail with foreign-key errors during migration. Run in parallel with a copy of the config that names another database (for example `sed 's/onechurch_symfony_test/onechurch_symfony_test_x/' phpunit.dist.xml > phpunit.x.xml`, then `phpunit -c phpunit.x.xml`; the bootstrap creates the database) and delete the copy afterwards.
- 2026-11-06 - C6a-c - Done together. cs, phpstan level 8 and deptrac (0 violations) are clean for everything C6 touched; 54 new tests (10 domain, 29 handler, 14 API, 1 two-connection concurrency), all green when run on their own. A parallel session was porting C7a (series) in the same checkout and the same test database, so a full `make test` run overlapped with theirs and failed in unrelated suites; re-run it once that work is in.
  - Delivered in `Sessions`: Domain `Attendance` (arrive, `issueLabel`, `failedAttempt`/`throttled`, `collect`, `transfer`), `AttendanceEvent`, `AttendanceSegment`, `AttendanceRepository`; Application `AdmissionPolicy`, `SessionChildPreparation`, `Handover` + `HandoverOutcome`, `SessionSupportScope`, views (`AttendanceView`, `LabelIssue`, `SessionDayPage`, `SessionCloseout`, ...); commands `CheckInChild`, `CheckOutChild`, `SupervisorCheckout`, `TransferChild`, `IssuePickupLabel`; queries `GetAttendance`, `GetAttendanceEvents`, `GetAttendanceSegments`, `GetExpectedChildren`, `GetPickupCollectors`, `GetRoster`, `GetStationRoster`, `GetSessionDay`, `GetSessionCloseout`; Infrastructure `DbalAttendanceStore` (reader + repository), `DbalSessionDayStore`, `DbalSessionCloseoutStore`, `SecurePickupTokens`, `DbalSessionIncidents::counts`; Presentation `AttendanceController`, `AttendanceJson`, and station routes on `StationController`.
  - Routes (`/api/children`): `POST /attendance` (station), `POST /attendance/labels` (station), `POST /attendance/checkout` (station), `POST /attendance/supervisor-checkout` and `/attendance/transfers` (supervise), `GET /sessions/{id}/roster` and `/expected-children` (station), `GET /attendance/{id}` and `/collectors` (station), `GET /attendance/{id}/events` (supervise), `GET /attendance/{id}/segments` (read), `GET /sessions/{id}/closeout` (manage), `GET /station/sessions/{id}/attendance` and `/day` (station). Station routes also require a present assignment or `children.supervise`.
  - **Lock orders (asserted with `CallLog`):** check-in = child person row, child guard row, session row, then rules, then writes; checkout/supervisor checkout = unlocked attendance read, child and collector (people by id) and guard, session, attendance row; transfer = child and guard, both sessions in id order, attendance row, target session again; label = child, session, attendance. Admission rules run under those locks.
  - **Wrong pickup codes are committed.** Java returned a `Decision` out of the transaction so a failed attempt is saved before the error is raised. `Handover` returns `HandoverOutcome`, the controller calls `->attendance()` after the bus transaction has committed, so the failed-attempt counter and `pickup.label.rejected` audit survive (`AttendanceApiTest` proves it over HTTP: 400 four times, then 429, with the counter at 5; the right code is refused while locked and works after the window).
  - Exactly-one-wins: V6 has the partial unique indexes `children_one_open_attendance` and `children_one_open_segment`. `SessionsInfrastructureTest` runs two real connections: the second insert queues behind the first (55P03 with a lock timeout), then fails with a unique violation after the first commits.
  - **Seam closed:** `PendingSupportSessionScope` deleted, `SessionSupportScope` implements `SupportSessionScope` (present staff, and the child placed in the class on the session date or checked in). `ParticipationSafeguardingCouplingTest` now asserts no placeholder remains. `AttendanceApiTest` covers allow/deny paths and that refusals leave no `support.accessed` audit.
  - Participation gained `enrolmentAt`, `assignmentAt`, `expectedChildren` on `ParticipationReader`; Safeguarding gained `SafeguardingReader::validConsents`. Sessions uses the returned views by inference, without importing them, so `ModuleBoundaryTest` still holds.
  - Deviations: `SessionChildPreparation` asks the readers per child (at most 100 per page) instead of Java's bulk readiness facts, because Symfony's `ReadinessFacts` carry no validity windows and preparation needs consent and authority to cover the whole session; `AttendanceReader::checkedIn` replaces Java's "latest 100 attendances" scan for support access; request DTO `expectedVersion` is required (missing is 400); the pickup code field and check-out request redact themselves in dumps; `POST /attendance/labels` sends `Cache-Control: no-store`.
  - Not ported here: `issuemychildpickuplabel` (member self-service, C8), `GetChildAttendance` and reporting views (C11, they live in the Java `reporting` package).
  - Java tests mapped: no Java handler tests exist for these slices; parity is covered by `AttendanceTest`, `AdmissionHandlersTest`, `HandoverHandlersTest`, `LabelAndTransferHandlersTest`, `AttendanceApiTest` and `SessionsInfrastructureTest`.
  - Next session notes: C8 (member family views) can reuse `AttendanceReader`, `PickupTokens` and `Attendance::issueLabel`; its label route must repeat the grant, authority and window checks of `IssueMyChildPickupLabelHandler` (expiry = min of now+5 minutes, own authority end, grant end, grant authority end). C9 should replace `DbalSessionIncidents` with the Incidents contract. Re-run the whole suite once C7a lands.
- 2026-11-06 - C5 - Done. `make check` green (cs, phpstan level 8, deptrac 0 violations), 532 tests (31 new).
  - Delivered in `Sessions`: `Worker::register`/`review`; `SessionRepository` gained `workerForUpdate`, `insertWorker`, `updateWorker`, `saveSessionStaff`; `SessionReader::presentElsewhere`; `VolunteerStaffLinkWriter` (implemented by `DbalVolunteerStaffLinks`); commands `SetSessionStaff`, `RegisterWorker`, `ReviewWorker`, `ImportServingStaff`; queries `GetWorker`, `ListServingCandidates`; controllers `WorkerController` (`children.safeguarding`) and `SessionStaffController` (`children.manage`).
  - Routes: `POST /api/children/workers` and `PUT /workers` (204), `GET /workers/{personId}`; `PUT /sessions/staff` (204), `GET /sessions/{id}/serving-candidates`, `POST /sessions/{id}/serving-staff` (204).
  - Lock orders (asserted with `CallLog`): staffing = session row, worker row, overlap check, version check, save, view, session update, audit. Register/review = person then child guard (`lockChild`), then the worker row (review). Import = assess, lock person, lock session, assess again under lock, link, then the staffing handler (called directly, so it shares the bus transaction). An open session whose staffing then needs review moves to NEEDS_REVIEW; a planned one stays planned.
  - Rules as Java: absence is never checked against other sessions; a second registration is a 409 (primary key); renewing needs an adult and an active account, revoking needs neither; revoking keeps `verifiedFrom` and stamps `revokedAt`; renewing sets `verifiedFrom` to now and clears revocation. The candidates list keeps only LEADER/ASSISTANT responsibilities and flags registered workers; `StaffingUnavailable` becomes the Java conflict message.
  - `SessionStaffingApiTest` runs the whole import against real V14 rows (volunteer, role approval, credential, availability, shift, assignment) and checks the link row, the session view and a version-moved 409.
  - Deviations: DTO `expectedVersion` required (missing is 400); `role` outside LEADER/ASSISTANT is a 400 from the DTO; Java's `Worker` JSON is reproduced field by field (`personId`, `accountId`, `verifiedFrom`, `screenedUntil`, `trainedUntil`, `evidenceReference`, `verifiedBy`, `revokedAt`, `version`).
  - Java tests mapped: no dedicated Java unit tests for these handlers; parity is covered by `StaffingHandlersTest` and `SessionStaffingApiTest`.
  - Next session notes: C6a ports admission (`checkinchild`, `AdmissionPolicy`, `SessionChildPreparation`, `getsessionday`/`SessionDayReader`, station attendance roster, expected children) and grows `AttendanceReader` and `DbalAttendanceStore` beyond `occupancy`. Write the ordered-lock tests first (person rows by id, child guard, session row, attendance). Use `MinistrySession::requireArrivalWindow` and `SessionOperations::requireSpace`, already ported and unit-tested. `SessionApiTest::staff()` still seeds workers by SQL and could use `POST /workers` now.
- 2026-11-06 - C4 - Done. `make check` green (cs, phpstan level 8, deptrac 0 violations), 501 tests (83 new).
  - Delivered under the new module `src/ChildrenMinistry/Sessions`: Domain (`MinistrySession` with `plan`/`withStatus`/`requireArrivalWindow`, `SessionStatus`, `StaffRole`, `SessionStaff`, `Worker` (read-side only, `eligible`), `CapacityPolicy`, `SessionRepository`); Application (`SessionOperations` with `view`/`date`/`requireSpace`, `StaffScope`, `SessionWindow`, `SessionView`/`StaffView`; commands `CreateSession`, `SetSessionStatus`; queries `GetSession`, `ListSessions`, `ListStationSessions`, `GetStationSession`); contracts `SessionReader`, `AttendanceReader`, `SessionIncidents`, `VolunteerStaffLinks`; Infrastructure (`DbalSessionStore`, `DbalAttendanceStore`, `DbalSessionIncidents`, `DbalVolunteerStaffLinks`, `ServingSessionActivities`, `ServingActivityGuard`); Presentation (`SessionController`, `StationController`, `SessionJson`). No migrations (V4 already applied).
  - Routes: `POST /api/children/sessions` (201 `{id}`) and `PUT /sessions/status` (204) need `children.manage`; `GET /sessions` and `GET /sessions/{id}` need `children.read`, `GET /station/sessions`, `GET /station/sessions/{id}` (`children.station`; a non-supervisor must be a present, unrevoked worker on the session). Locations and rooms were already ported in C1 (`VenueController`).
  - `ActivitySource` (kind `CHILDREN_SESSION`) is picked up by the `volunteering.activity_source` tag, so the "source is not available" message from P2b no longer applies to sessions. `scheduleRevision` and `venueLabel` are null, exactly as in Java (the Java adapter publishes only id, label, interval and active), so the revision is the id plus interval fingerprint; `SessionsInfrastructureTest` proves a reschedule or cancellation invalidates a shift. `ServingActivityGuard` locks unique `CHILDREN_SESSION` ids in byte order with `FOR UPDATE`; a second connection blocks (55P03).
  - Rules ported with Java's order: `SetSessionStatus` locks the session, validates the reason (1-500), refuses CLOSED/CANCELLED while `occupancy > 0`, requires `children.incidents.manage` plus `acknowledgeIncidents` to close with unresolved incidents (and audits the acknowledgement first), refuses OPEN while `staffingNeedsReview`, then applies the transition (terminal states stay terminal; nothing returns to PLANNED) and audits. Opening check therefore comes before the terminal check, as in Java. Exclusion constraints reject overlapping non-cancelled sessions in a room or class (409; a cancelled session frees its slot).
  - Session views: capacity is `min(seatLimit, room capacity, ready adults * childrenPerStaff)`, 0 without a leader or enough adults; a worker's issues come from the linked volunteer assignment (`StaffingDirectory`, `StaffingUnavailable` becomes an issue), screening/training, account activity, person availability and adulthood on the session's local day. `date()` uses the location's time zone (checked across midnight).
  - Seams (no `Pending*` placeholders added): (1) `DbalAttendanceStore` implements only `AttendanceReader::occupancy`; C6a grows the contract. (2) `DbalSessionIncidents` counts `children_incidents` directly (Java's `IncidentReader.counts` query); C9 publishes the Incidents contract and the adapter then delegates. (3) `DbalVolunteerStaffLinks` is read-only; C5 adds the writer. (4) `Worker` has `reconstitute` only; C5 adds registration and review. (5) `SessionRepository` has no staff or worker writes yet.
  - Not ported here (assigned to later sessions): `getsessionday`/`SessionDayReader`/`SessionChildPreparation` and `GET station/sessions/{id}/attendance` and `.../day` (they need attendance and admission, so C6a); `setsessionstaff`, workers, serving candidates (C5); series and team planning (C7); `PendingSupportSessionScope` (C6).
  - Deviations: request DTO `expectedVersion` is required (missing is 400); an unknown `status` string is a 400 from the DTO (Java would also 400); `upcoming()` on the activity source filters inactive sessions before paging (Java paged first, then filtered, so a page could come back short); the `Choice` constraint uses the Symfony 8 `choices:` named argument.
  - Java tests mapped: Java has no dedicated session unit tests; its sessions API behaviour is covered by `MinistrySessionTest`, `CapacityAndWorkerTest`, `SessionHandlersTest`, `SessionOperationsTest`, `SessionApiTest` (401/403 on every route, overlap 409s, window limits, staffing, occupancy and incident gates, station scoping) and `SessionsInfrastructureTest`. Note `manage` implies `read` (`IdentityRules`), so a manager can read sessions.
  - Next session notes: C5 adds `setsessionstaff` (PUT `/sessions/staff`, `children.manage`; lock order session then worker via `SessionRepository::ministrySessionForUpdate`/`workerForUpdate`, `presentElsewhere` overlap check), `registerworker`/`reviewworker`/`getworker` (`children.safeguarding`, `POST/PUT /workers`, `GET /workers/{personId}`), `listservingcandidates`, `importservingstaff` and `VolunteerStaffLinkWriter`. Reuse `Worker::reconstitute`'s validation for `register`/`review`, `StaffingDirectory::candidates` and its `StaffingUnavailable` mapping, and the `SessionFixture` in `tests/ChildrenMinistry/Sessions/Application`. `SessionApiTest::staff()` seeds workers by SQL; switch it to the API once C5 lands.
- 2026-11-06 - C3d - Done. `make check` green (cs, phpstan level 8, deptrac 0 violations), 418 tests (3 new). No production code changed.
  - Audit result: the cycle was already closed by C3a-c, because each session replaced its seam with a real implementation. `PendingChildReadiness` went in C3b; consent policy persistence moved into Safeguarding; the programme write side (`programmeForUpdate`, `updateProgramme`, `withPolicy`) lives in Participation's Domain. Nothing was left to replace here except the Sessions-dependent seam below.
  - Added `ParticipationSafeguardingCouplingTest`: (1) Participation imports from Safeguarding only `Application\Contract\*`; (2) Safeguarding imports from Participation only `Application\Contract\*` plus `Domain\Programme` and `Domain\Repository\ParticipationRepository`; (3) the only `PLACEHOLDER`/`Pending*` class left in the two modules is `PendingSupportSessionScope`. `ModuleBoundaryTest` already enforces the general sibling rules for every module.
  - **Remaining seam (owned by Sessions):** `PendingSupportSessionScope` (see C3c). C6 must implement `SupportSessionScope` in Sessions and delete the placeholder, then drop the third test's allowlist entry. Participation's 12 Java references to Sessions are not yet needed: nothing in the ported Participation slice calls Sessions, and C4-C6 will consume Participation contracts (`ParticipationReader`, `ProgrammeRosterReader`) instead.
  - Next session notes: C4 (Sessions A) needs P2a contracts (`ActivitySource`, `ServingActivityLocks`), Participation's `VenueReader`/`ParticipationReader`, V4. Remember `ChildrenMinistry/Sessions` is a new module directory: `ModuleBoundaryTest` and deptrac pick it up automatically.
- 2026-11-06 - C3c - Done. `make check` green (cs, phpstan level 8, deptrac 0 violations), 415 tests (24 new).
  - Delivered in `Safeguarding`: Domain `SupportRecord` (`create`/`revise`/`expiredOn`; `__debugInfo` hides the payload) and `SupportRepository`; `SupportReader` contract with `SupportRecordView` (also redacted in dumps); commands `SaveChildSupport`, `AccessChildSupport`, `PurgeExpiredSupport`; `DbalSupportStore` over V6; `SupportController` with `PUT /api/children/support` (204; children.manage + children.support), `POST /support/access` (children.support; children.manage when no session) and `POST /support/purge` (children.support + children.supervise; returns the count as a bare JSON number, as Java).
  - **Reads are commands:** `AccessChildSupport` goes through `CommandBus` so the `support.accessed` audit row commits with the read; it is the only class that calls `SupportReader::supportRecord` (enforced by `RestrictedPayloadBoundaryTest`, which also forbids any code outside Safeguarding from naming the support types, so Reporting in C11 cannot reach them). Audit rows carry no payload; failures (403, 404, 400) write no audit row and echo no care details (API-tested).
  - **Seam for C3d/C6:** session-scoped access (`sessionId` present) needs Sessions' staff assignment and attendance. Safeguarding owns a `SupportSessionScope` contract; the `PendingSupportSessionScope` placeholder denies every session-scoped read with 403, so today only `children.manage` + `children.support` holders can read support records. Sessions replaces it (it checks: actor assigned to the session, session exists, child enrolled in its class on the session date or still checked in).
  - Decision: no scheduler for `PurgeExpiredSupport`. Java only exposes the endpoint, so parity keeps it an explicit, supervised action. Add a `Schedule.php` entry later only if operations asks, using a system actor.
  - Lock order as Java: save locks the child (person row, guard row), then reads the support row `FOR UPDATE`, then checks dates and version. Purge locks each child then re-checks expiry under the lock (a renewed record survives; tested with a stale listing).
  - Deviations: `expectedVersion` required (missing is 400). A bad first-save version (non-zero) is 409, as in Java. Unlike Java, a malformed `sessionId` is a 400 at the DTO.
  - Java tests mapped: none exist for support in Java's safeguarding package; behaviour is covered by `SupportRecordTest`, `SupportHandlersTest`, `SupportApiTest` and `RestrictedPayloadBoundaryTest`.
  - Next session notes: C3d replaces seams: `PendingSupportSessionScope` is left for Sessions (C6), so C3d's scope is the participation/safeguarding cycle only. Remaining Participation references to Sessions/Safeguarding are listed in the C2 notes. Add `ModuleBoundaryTest` expectations as planned, and re-check that Participation does not import Safeguarding Application types other than contracts.
- 2026-11-06 - C3b - Done. `make check` green (cs, phpstan level 8, deptrac 0 violations), 391 tests (18 new).
  - Delivered in `Safeguarding`: Domain `Consent` (`record` enforces authority/policy/acceptance rules with Java's single message) and `ConsentPolicy` (SHA-256 hash check); commands `RecordConsent`, `RevokeConsent`, `PublishConsentPolicy`; queries `GetConsentPolicy`, `GetChildReadiness`; `ReadinessAssessor` (implements the `ChildReadiness` contract, plus `assess` for the single-child endpoint); `DbalReadinessStore` (selects only the columns readiness needs); consent and policy methods on `SafeguardingRepository`/`SafeguardingReader`/`DbalSafeguardingStore`. Routes: `POST consents`, `POST consents/revoke` (children.safeguarding), `POST consent-policies` (children.manage), `GET consent-policies/{id}` and `GET programmes/{programmeId}/children/{childId}/readiness` (children.read).
  - **Seams closed:** `PendingChildReadiness` deleted, roster `readiness` is now `{ready, completed, total}`; `GET safeguarding/{childId}` now returns real `consents`.
  - Participation gained `Programme::withPolicy` (version check, advances version) and `ParticipationRepository::programmeForUpdate`/`updateProgramme`. Publishing locks the programme row, checks the version, then writes policy and programme. Java had consent policy persistence inside Participation; here Safeguarding owns it, since it owns the type. Routes are unchanged.
  - Boundary decision: the readiness reader returns Safeguarding's own fact records (`ReadinessEnrolment`, `ReadinessPlacement`, ...) instead of Participation views, because views live outside `Application\Contract` and `ModuleBoundaryTest` forbids importing them. It reads participation tables directly in its own adapter (as Java's `JooqReadinessStore` does). Readiness uses `>` for the end of consent/authority windows (Java), while `validAuthorizations` uses `>=`; both kept as in Java.
  - Deviations: required `expectedVersion` (missing is 400); policy `label`/`documentReference`/`contentHash` over 2000 characters are 400 at the DTO, over the domain limits 400 from the domain. Duplicate policy label surfaces as 409 through the V3 unique constraint.
  - Java tests mapped: `ChildReadinessApiIntegrationTest` -> `ConsentAndReadinessApiTest` (all four cases; the "care records" check uses evidence/guardian/collector markers). `GetChildReadinessHandlerIntegrationTest` (28 lines) is covered by the permission and 404 cases there.
  - Next session notes: C3c ports confidential support records (`SupportRecord`, `SupportRepository`, `SupportReader`, `SaveChildSupport`, `AccessChildSupport`, `PurgeExpiredSupport`, `SupportController`, V6 tables). Permission is `children.support`; every read is audited; add the dedicated restricted-payload tests (and a boundary test that Reporting never imports support types, which C11 will extend). `PurgeExpiredSupport` needs a trigger decision (console command vs `Schedule.php`); record it in the log.
- 2026-11-06 - C3a - Done. `make check` green (cs, phpstan level 8, deptrac 0 violations), 373 tests (29 new).
  - Delivered under `src/ChildrenMinistry/Safeguarding`: Domain (`GuardianAuthority`, `PickupAuthorization`, `SafeguardingRepository`); Application (`VerifyGuardian`, `RevokeGuardian`, `AuthorizePickup`, `RevokePickupAuthorization` commands, `GetSafeguarding` query, `SafeguardingReader` contract with `GuardianAuthorityView`/`PickupAuthorizationView`/`SafeguardingView`); `DbalSafeguardingStore` (repository + reader over V3/V5, no migrations); `SafeguardingController` (5 routes under `/api/children`: `POST guardian-authorities`, `POST guardian-authorities/revoke`, `POST pickup-authorizations`, `POST pickup-authorizations/revoke`, `GET safeguarding/{childId}`), all `children.safeguarding`.
  - Lock order as Java: `ParticipantAccess::lockChild` (people by id, then guard row) before anything else; revoke handlers read the row unlocked to learn the child, lock, then re-read `FOR UPDATE`. `AuthorizePickupHandler` locks child and collector, checks ages, programme, then reads the authority. Asserted per handler in `AuthorityHandlersTest`.
  - Overlapping active grants for one child, collector and programme are rejected by the V5 exclusion constraint and surface as 409 (no PHP-side check, as in Java). The `(child_id, authority_id)` FK keeps a grant tied to the child's own authority.
  - Added `ChildrenRules::window` (instant windows; the date `period` stays for enrolments) and `Json::parseInstant` (ISO-8601 with `Z` or offset, microsecond truncation, 400 on anything else). Instants are `DateTimeImmutable`, written with `Timestamps`.
  - **Seams for C3b:** (1) `GET safeguarding/{childId}` returns `consents: []` (marked PLACEHOLDER in `SafeguardingJson`); C3b adds `ConsentView` to `SafeguardingView` and the reader, and fills it. (2) `SafeguardingRepository`/`SafeguardingReader` have no consent methods yet (`consentForUpdate`, `validConsents`, ...). (3) `PendingChildReadiness` is untouched; roster `readiness` is still `null`.
  - Deviations: request DTO `expectedVersion` is a required int (missing is 400, per AGENTS.md); Java's `@Min(0) long` defaulted to 0. Evidence over 255 chars is a 400 from the domain (Java's DTO allowed 2000 but the domain limit is 255). Malformed instants return the message "Timestamps must use the ISO-8601 instant format.".
  - Java tests mapped: no safeguarding-specific Java test class exists; pickup grants are exercised by `PickupApiIntegrationTest` (sessions, C6b) and `MemberFamilyApiIntegrationTest` (C8). Parity is covered by `AuthorityDomainTest`, `AuthorityHandlersTest` and `SafeguardingApiTest` (401/403 on every route, 400/404/409, rollback leaves no guard row).
  - Next session notes: C3b ports `RecordConsent`, `RevokeConsent`, `PublishConsentPolicy`, `GetConsentPolicy`, `GetChildReadiness` and the `ChildReadiness` implementation (`ChildReadiness.java`, 94 lines; replace `PendingChildReadiness`, then update `ProgrammeRosterApiTest::testRosterShowsNamesStatusAndCurrentClassesWithoutSensitiveDetail`), plus `Programme.currentPolicyId` updates. Reuse `ChildrenRules::window` and `Json::parseInstant`. Consent rows need `findGuardianAuthority` (already on the repository) to verify authority covers the consent window.
- 2026-11-06 - P2b - Done. `make check` green, 305 tests (about 30 new, plus P1b's from the parallel session).
  - Delivered under `src/Volunteering`: Domain (`Availability`, `AvailabilityRule`, `AvailabilityException`, `ValidityWindow`, `ServingRules`, `VolunteerException`); Application (`PublishedStaffingDirectory implements StaffingDirectory`, `VolunteerReadiness`, `ActivityRegistry`, `Fingerprint`, `Readiness`); read contracts `VolunteerReader`, `TeamReader`, `RosterReader` with view records; DBAL adapters `DbalVolunteerReader`, `DbalTeamReader`, `DbalRosterReader` over V14 (read-only, no migrations).
  - `ActivitySource` implementations are collected by tag `volunteering.activity_source` (`_instanceof` in `services.yaml`) into `ActivityRegistry`. **No implementations exist yet**, so every non-STANDALONE shift reports "This activity source is not available..." until C4 adds the `CHILDREN_SESSION` source. Wording kept verbatim from Java (including "in the Java application").
  - `Fingerprint` is byte-compatible with Java (`int32` big-endian length prefix, `Instant.toString` formatting) so `serving_shifts.source_revision` written by Java still matches. Tested with an independently computed digest.
  - Availability DST handling matches Java: local times in a gap or overlap are skipped, never granted. Rule times and dates are plain strings in the domain (`H:i:s`, `Y-m-d`).
  - Deviations: a missing shift for an assignment is a `VolunteerException` (hence `StaffingUnavailable`); Java threw an unchecked `NoSuchElementException`. Readers return views, not aggregates; `Shift` creation invariants (7-day max, 1-100 positions) are not re-validated on read.
  - Not ported (belongs to the rest of Volunteering, unported): writers, invitations, reminders, `ServingActivityLocks` use inside Volunteering, `ActivityStaffing`, member duty views, volunteer search/listing.
  - Java tests mapped: `AvailabilityTest` -> `AvailabilityTest` (plus gap case); readiness and `PublishedStaffingDirectory` have no dedicated Java unit tests, so they are covered by `VolunteerReadinessTest`, `PublishedStaffingDirectoryTest` and `DbalStaffingReadersTest` (real schema).
  - Next session notes: C4 must publish an `ActivitySource` (kind `CHILDREN_SESSION`; include `scheduleRevision` and `venueLabel` consistently with the Java `ServingSessionActivities`) and a `ServingActivityLocks` implementation. C5 injects `StaffingDirectory` and catches `StaffingUnavailable`. `candidates` assumes `RoleView::childrenRole` values NONE/LEADER/ASSISTANT.
- 2026-11-06 - P2a - Done. `make check` green, 211 tests (5 new).
  - Delivered `Volunteering/Application/Contract`: `StaffingDirectory` (`assess`, `candidates`), `StaffingAssessment`, `StaffingUnavailable`, `ServingActivityLocks`, `ServingActivity`, `ActivitySource`, `SourceActivity`. Contracts only; no adapters, no migrations, no services wiring.
  - Deviations from Java: ids are strings and instants `DateTimeImmutable`; Java's overloaded `upcoming` becomes one method with `?int $limit = null, int $offset = 0`; nested records became top-level readonly classes; `StaffingUnavailable` extends `RuntimeException` (as Java), so consumers must catch it and map to their own failure (Java does in `SessionOperations`, `ListServingCandidatesHandler`, `ImportServingStaffHandler`).
  - Behavioural notes for implementers: `ServingActivityLocks` implementations filter to their own kind, de-duplicate and lock in ascending id order (Java `ServingActivityGuard` locks `CHILDREN_SESSION` ids sorted by string); `candidates` throws `StaffingUnavailable` above 100 linked shifts and skips CANCELLED assignments; `assess` issues, in order: readiness issues, not CONFIRMED, wrong session link, role does not approve the responsibility, interval not covered.
  - Next session notes: P2b implements `StaffingDirectory` over V14/V10 tables (`PublishedStaffingDirectory` in Java, with readiness from `VolunteerReadiness`); C4 implements `ActivitySource` and `ServingActivityLocks` for `CHILDREN_SESSION`. Rest of Volunteering stays unported.
- 2026-11-06 - C2 - Done. Full `make check` green: 344 tests after review (333 at first delivery), phpstan level 8, deptrac, cs (P2b had landed by then).
  - Delivered: `Enrolment` and `ClassAssignment` aggregates (half-open date ranges via `CalendarDate`, `end(expectedVersion, now)`, "already ended" conflict); `ParticipationRepository` extended (`findClass`, enrolment and assignment insert/update/forUpdate, `endAssignments`); `ParticipationReader` extended (`enrolment`, `classAssignment`, `enrolments`, `assignments`) with `EnrolmentView`/`AssignmentView`; `ProgrammeRosterReader` + `DbalProgrammeRosterStore`; commands `EnrolChild`, `AssignClass`, `EndEnrolment`, `EndClassAssignment`; queries `ListEnrolments`, `ListClassAssignments`, `ListProgrammeChildren`; `EnrolmentController` and `ProgrammeRosterController` (7 routes).
  - **Seams (each listed so C3 can remove it):** (1) `ChildReadiness` contract owned by Safeguarding (`Safeguarding/Application/Contract`, with `ReadinessSummary`) is satisfied by the **placeholder** `Safeguarding/Infrastructure/PendingChildReadiness`, which returns no summaries, so the roster's `readiness` is always `null` for now. C3 must replace it (not extend it) and update `ProgrammeRosterApiTest::testRosterShowsNamesStatusAndCurrentClassesWithoutSensitiveDetail`, which currently asserts `readiness => null`. (2) Consent policy endpoints and `Programme.currentPolicyId` updates still belong to C3b.
  - Moved into shared code: `ParticipantAccess` (person lookup/availability and `lockChild`) -> `ChildrenMinistry/Shared/Application` (Java had it in sessions; it is pure and used by several modules); `ChurchCalendar` -> `Shared\Application` (still configured by `PEOPLE_TIME_ZONE`).
  - Lock order: people sorted by id, then the child's guard row (`ParticipantAccessTest`); handlers that start from an enrolment/assignment id read it unlocked to learn the child, lock the child, then re-read `FOR UPDATE`. **Review follow-up:** the first version of C2 only tested the helper in isolation. `EnrolmentHandlersTest` now asserts the exact sequence of reads, locks, writes and audit for all four handlers (permission first and nothing touched when denied; locks before the row is re-read; no write or audit when a rule or version check fails), and `ChildrenInfrastructureTest` proves with a second connection that the child guard row blocks until the holder commits (Postgres `55P03`).
  - Overlapping enrolments or placements are rejected by the existing V3 exclusion constraints and surface as 409 through the shared listener (no PHP-side overlap check, as in Java).
  - Deviations: request DTOs parse dates with `CalendarDate::parse`, so a malformed or missing date is a 400 with the message "Dates must use the YYYY-MM-DD format."; Java's `@NotNull LocalDate` produced Spring's generic 400.
  - Java tests mapped: `ParticipationApiIntegrationTest` (enrolment/age, placement fit, ended enrolment + stale edit, ending enrolment ends placements + clean re-enrolment) -> `EnrolmentApiTest`; `ProgrammeRosterApiIntegrationTest` -> `ProgrammeRosterApiTest`; `ListProgrammeChildrenHandlerIntegrationTest` (anonymous and revoked-permission rejection) -> permission cases in `EnrolmentApiTest` and `ParticipationApiTest`. The consent-policy and admission cases of those Java classes belong to C3b/C6a.
  - Review fixes: (1) API tests built "N years ago" with PHP `modify('-N years')`, which rolls 29 Feb to 1 Mar and would have failed on 2028-02-29; they now use `ChildrenApiTestCase::yearsAgo()` (the app's clamping `CalendarDate::minusYears`). (2) php-cs-fixer rewrites a class constant named `CLASS` to `self::class`, silently changing its meaning: never name a constant `CLASS`. (3) Removed a roster test that did not test what it claimed.
  - Known gaps, not defects: no concurrent-request test at HTTP level (the lock itself is proven at the store level); `PendingChildReadiness` is still a placeholder (seam 1 above).
  - Next session notes: C3a starts safeguarding with guardian authority and collector (pickup) grants: tables `children_guardian_authorities` (V3), `children_pickup_authorizations` (V5), V28 index/view. Java sources under `childrenministry/safeguarding`. Authority checks need `ChildLocks`/`ParticipantAccess` (already shared) and `MemberIdentity` only for the member views in C8.
- 2026-11-06 - C1 - Done. 271 tests green across `tests/ChildrenMinistry|Shared|People|IdentityAccess|Architecture`, phpstan level 8 clean on those paths, deptrac 0 violations.
  - **Caveat:** a full `make check` could not be run cleanly because the parallel P2b session had unfinished edits in `src/Volunteering` (2 phpstan `list<...>` errors in `ActivityRegistry` and `DbalTeamReader`). Nothing in C1 touches Volunteering. Re-run `make check` once P2b lands.
  - Shared kernel (`ChildrenMinistry/Shared`): `ChildrenException` (P0), `ChildrenRules` (`text`, `optional`, `period`, `covers`, `version`, `child`, `adult`, `page`; `child`/`adult` return the validated birth date), contracts `ChildLocks` and `ChildrenAudit` (returns the audit row id), `RequestFingerprint`, adapters `DbalChildLocks` (insert guard row `ON CONFLICT DO NOTHING`, then `FOR UPDATE`) and `DbalChildrenAudit`.
  - Moved to the app-wide `Shared` kernel because a second context now needs them: `CalendarDate` (+ `plusYears`, `plusMonths` clamped like Java's `LocalDate`, `equals`; throws new `InvalidInputException`) and `LikePattern`. `DbalAuditTable::append` now returns the new row id.
  - Participation: `Programme`, `MinistryClass` (`requireAge`), `Location`, `Room` aggregates with private constructors; one `ParticipationRepository` (insertProgramme, insertClass; C2 and C3b add the rest) and `VenueRepository`; read models `ProgrammeView`, `ClassView`, `LocationView`, `RoomView` behind the published `ParticipationReader` and `VenueReader`; commands `CreateProgramme|Class|Location|Room`; queries `GetProgramme`, `ListProgrammes`, `ListClasses`, `ListLocations`, `ListRooms`; `ProgrammeController` and `VenueController` (9 routes under `/api/children`).
  - Decisions/deviations: `Location`/`Room` live in Participation although Java keeps their repository in the sessions package (avoids a Participation-to-Sessions dependency). Request DTOs carry no range or length rules, so Java's `@Min/@Max` API bounds (for example `minStaff` up to 10000) are replaced by the domain rules (1-100); out-of-range input is still a 400. `GET /rooms` without `locationId` returns an empty list, as in Java.
  - Java tests mapped: `ChildrenRulesTest` -> `ChildrenRulesTest` + `ParticipationDomainTest`; programme/class/location/room parts of `ParticipationApiIntegrationTest` and `SessionApiIntegrationTest` -> `ParticipationApiTest`; plus `RequestFingerprintTest`, `ChildrenInfrastructureTest`. New test helper `IdentityApiTestCase::accountWith(username, permissions)`.
  - Not in C1 (as planned): consent policies (C3b), enrolments, class assignments, programme roster (C2).
  - Next session notes: C2 starts with the seam list (the 13 participation-to-safeguarding and 12 participation-to-sessions references in Java). Participation Java handlers `EnrolChild`, `AssignClass`, `EndEnrolment`, `EndClassAssignment` need `ChildLocks`, `PersonDirectory` (birth date via `PersonDirectoryEntry::birthDate`, now a shared `CalendarDate`) and `ChildrenRules::child`. `Programme` needs a `withPolicy` change in C3b (version bump); its `version` is a plain int because the table has no `updated_at`.
- 2026-11-06 - P1b - Done. `make check` green, 228 tests (22 new).
  - Delivered: `GetMyProfile` query; `UpdateMyProfile`, `SaveMyContact`, `RemoveMyContact` commands; `MemberProfileController` (`GET|PUT /api/member/profile`, `PUT /api/member/profile/contacts`, `DELETE /api/member/profile/contacts/{contactId}?expectedVersion=`). No staff permission: any authenticated account, with the handlers requiring a verified, active, available person. Profile responses are `no-store, private`.
  - Handlers call `MemberIdentity::requirePerson`, then `MemberIdentityGuard::lockPerson` (returns the person id, which is never taken from the request), then `requirePerson` again, then `requireAvailable`. Self-edits go through `PersonDetails::withProfile`, so date of birth, life status and membership type stay staff-managed.
  - Addition to the People read side: `PersonReader::activeContact(personId, contactId)` (ownership check; someone else's or an ended contact is "not found").
  - Shapes: `{profile: {id, details, membershipType (string), version, contacts (active only)} | null, titles: [...]}`; unverified accounts get `{profile: null, titles: []}`. An archived person is reported as unverified (403 on edit) because `activePerson` requires an available person.
  - Deviations: unknown JSON properties on the profile body (`dateOfBirth`, `membershipType`, `personId`) are ignored, as in Java. Java's `donor-access` assertions in `MemberProfileApiIntegrationTest` belong to Giving and are not ported; the equivalent veto path is covered by `VerifyMemberLinkHandlerTest` (`PersonBindingPolicy`).
  - Java tests mapped: `GetMyProfileHandlerSecurityTest`, `UpdateMyProfileHandlerSecurityTest`, `SaveMyContactHandlerSecurityTest`, `RemoveMyContactHandlerSecurityTest` -> `MemberProfileHandlersTest` (spoofed actor, unverified, lock order) + `MemberProfileApiTest`; `MemberProfileApiIntegrationTest` -> `MemberProfileApiTest`.
  - Lesson: sessions running in parallel share one test database (`onechurch_symfony_test`); `DatabaseTestCase` rebuilds the schema at the start of each run, so two concurrent `phpunit` runs can fail in `setUp` with foreign-key errors on the seed rows (seen once while P2a was running). Re-run alone; do not treat it as a code failure.
  - Next session notes: P2a (Volunteering contracts) or C1 (Children shared kernel + Participation core). C8 (member family views) can now use `MemberIdentity` and `MemberIdentityGuard`.
- 2026-11-06 - P1a - Done. `make check` green, 206 tests (29 new).
  - Delivered: all ten member-identity contracts (`MemberIdentity`, `MemberIdentityGuard`, `AccountDirectory`, `AccountIdentityGuard`, `AccountNames`, `MemberAdmissionGuard`, `MemberLinkReader`, `PersonBindingPolicy`, `ResponsibleAccountDirectory`, `DeviceSessionReader`), `MemberLink` aggregate + `MemberLinkRepository`, `VerifiedMemberIdentity`, `VerifyMemberLink`/`RevokeMySession` commands, `ListMemberAccess`/`ListMySessions` queries, `Published*` adapters, `DbalMemberLinkStore` (upsert + immutable history row), `DbalSessionStore::devices`, and endpoints `GET|PUT /api/member-access[/{accountId}]`, `GET /api/me/sessions`, `DELETE /api/me/sessions/{sessionId}`.
  - Contracts return strings/read models, not UUID objects or aggregates (`MemberAdmission`, `ResponsibleAccount`, `DeviceSession`, `MemberLinkSummary`).
  - `PersonBindingPolicy` implementations are collected by tag `identity.person_binding_policy` (set by `_instanceof` in `services.yaml`) and injected into `VerifyMemberLinkHandler` as an iterable. **No implementations exist yet**; Giving/Payments will add theirs when ported.
  - Lock order in `VerifyMemberLink`: account root, then member link, then person; permissions re-checked after the locks. `VerifyMemberLink` and `ListMemberAccess` depend on `PermissionChecker`, so they are unit-tested with fakes; the two session handlers use the concrete `Authorization` (`requireSelf`) and are covered through the API.
  - Deviation: `Cache-Control` on the member-access and sessions endpoints is `no-store, private` (Symfony appends `private`); Java sends `no-store`.
  - Java tests mapped: `VerifyMemberLinkHandlerSecurityTest` -> `VerifyMemberLinkHandlerTest` + `MemberAccessApiTest`; `ListMemberAccessHandlerSecurityTest` -> `MemberAccessApiTest`; `RevokeMySessionHandlerSecurityTest`, `ListMySessionsHandlerSecurityTest`, `DeviceSessionsApiIntegrationTest` -> `DeviceSessionsApiTest`; plus `MemberLinkTest`, `VerifiedMemberIdentityTest`.
  - Not ported (not needed by Children): mobile checkout tokens (`payments_mobile_checkouts`, Payments context).
  - Next session notes: P1b needs `MemberIdentity::requirePerson` and `MemberIdentityGuard::lockPerson` (both now available) in the four People `*MyProfile`/`*MyContact` handlers. `lockPerson` returns the person id after locking the link row; People handlers then `findForUpdate` that person.
- 2026-11-06 - P0 - Done. Nested-module tooling in place and proven with a throwaway probe module (route, bus handler registration, container excludes, deptrac violation detection), then deleted. `make check` green, 177 tests.
  - `config/services.yaml`: second set of excludes for `src/*/*/Domain|Application/Command|Query`.
  - `deptrac.yaml`: layer regexes accept an optional module segment (`App\<Context>\[<Module>\]<Layer>\`). Same-layer dependencies are always allowed by deptrac, so cross-module Domain sharing needs no rule; the walls are tests.
  - `tests/Architecture/BoundaryRules` holds the wall logic as pure functions (tested in `BoundaryRulesTest`); `ContextBoundaryTest` now accepts `App\Other\[Module\]Application\Contract\X`; new `ModuleBoundaryTest` (modules in `BoundaryRules::MODULAR_CONTEXTS`, currently `ChildrenMinistry`). Siblings: Domain and `Application\Contract` only; `Shared` module depends on no sibling.
  - `ChildrenMinistry/Shared/Domain/ChildrenException` (invalid, missing, conflict, forbidden, throttled). **Deviation in the shared kernel:** `ContextException::retryAfterSeconds()` (default 60) so Children's throttles return `Retry-After: 900` like Java; `ApiExceptionListener` uses it. Java uses THROTTLED only in sessions `Handover` (pickup attempts).
  - Not done in P0 (belongs to C1): `ChildrenRules`, `ChildLocks`, `ChildrenAudit`, `RequestFingerprint`, the children audit writer and its table check.
  - Next session notes: P1a and P2a can run in parallel. C1 starts from the Java `childrenministry/shared` listing: `ChildrenRules`, `ChildLocks`, `ChildrenAudit` (record returns a UUID and takes a reason), `RequestFingerprint` (SHA-256 of length-prefixed values), `ChildrenRecords`.
- 2026-11-06 - planning - Accepted recommendations: D1 staffing slice only, D2 nested modules, D3 include member self-service (mobile uses it), D5 Symfony Mailer. D4 deferred to C7a.
- 2026-11-06 - planning - Plan written. No code changes.
