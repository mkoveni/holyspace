# Porting Communities to Symfony

Status: **S1 (directory, membership, leadership), S2 (follow-ups), S3 (meetings), S4 (notices) and S5 (member views) and S6 (hardening) done 2026-11-07: all 31 of Java's routes; the port is complete.** Java
(`backend-java/src/main/java/org/onechurch/communities`) is the behavioural and API source of truth; the applied V18
migration defines the schema (V36 adds only a trigram index for the name search). Update the status board and the log
at the end of every session.

## Session prompt

> Continue the Communities port: read `backend-symfony/AGENTS.md` and this file, then do the next unchecked slice.
> Java is the specification. Finish with `make check` green and update this file.

## Scope

Ministry and group definitions, memberships, leadership, directory and member views, meeting scheduling through
Events, frozen rosters and attendance, follow-ups, history, reviewed notices and deliveries, and the published
contracts. Preserve Java's routes, JSON (record field names, nulls included) and messages.

## Status board

The original plan (C1-C6, by layer) is replaced by vertical slices so every session ends with working routes.

| # | Slice | Java source | Routes | Needs | Status |
|---|---|---|---|---|---|
| S1 | Directory, membership and leadership; `CommunityParticipation` | `CommunityManagement`, `CommunityViews` (directory part), `CommunityAccess`, `CommunityHash`, `JooqCommunityStore` (part), `JooqCommunityParticipation`, `CommunityDirectoryController` | 12 | - | [x] |
| S2 | Follow-ups | `GroupMeetings.followup/complete`, `CommunityViews.followups`, `CommunityFollowUpsController` | 3 | S1 | [x] |
| S3 | Meetings: preview, schedule through `Events\CommunityCalendar`, prepare (frozen roster), guests, attendance, finish, list, roster; `CommunityOccurrenceMovement` + `CommunityScheduleListener` (Events tagged services) | `GroupMeetings`, `CommunityViews.meetings/roster`, `CommunityMeetingsController`, infrastructure adapters | 8 | S1 | [x] |
| S4 | Notices: contacts, review, approve, deliveries; `CommunityDeliveryEligibility`, `CommunityTemplatePolicy` (template `communities.notice` is seeded by V18) | `CommunityMessaging`, `CommunityMessagingController` | 4 | S1 | [x] |
| S5 | Member views: `/api/member/communities[/{id}]`, `/api/member/group-meetings[/{id}]` | `MemberCommunityViews`, `JooqMemberCommunityStore`, `MemberCommunitiesController` | 4 | S1, S3 | [x] |
| S6 | Hardening: two-session concurrency tests (parallel joins, meeting attendance), Java-captured hash vector, route parity, README/AGENTS | all | - | S1-S5 | [x] |

## Decision log

- Communities is its own bounded context at `src/Communities/` (flat, not modular).
- Preserve V18 and Java compatibility. Search uses `c.name ILIKE` with `LikePattern`, served by the V36 trigram index.
- Lock order (Java): a person (People `PersonDirectory::lock`) before the community row; the community row serializes
  membership and leadership changes; a parent ministry before its group on update. `find*Unlocked` loads learn which rows
  to lock and are reloaded after the locks (`@phpstan-impure`).
- Readers return read models (`CommunityView`, `MemberView`, `LeadershipView`...), the repository returns aggregates.
- Meeting lock order (Java): a person (guests, attendance), then the Events occurrence (`CommunityCalendar::lock`), then
  the community row, then the meeting row, which serializes its roster. Scheduling lets Events take its own locks
  (`CommunityCalendar::create`) before the community row. `MeetingLocks` holds this order; `MeetingHandlersTest`
  asserts it.
- `Events\Application\Contract\CommunitySchedule` exposes `TimeOfDay` (an Events domain type), so Communities builds
  it only through `CommunitySchedule::of()` (wire text) and hashes it with `javaString()` (Java's record text).
  `MeetingCalendar` turns every `CalendarFailure` into a `CommunityException` with the same status and message, as
  Java's `CommunityExceptionHandler` does.
- `PermissionVoter` accepts `a|b|c` attributes (any of), so the controller can mirror Java's class-level
  `hasAnyAuthority('ministries.read','ministries.manage','ministries.lead')` before the body is read.

## Log

### S1 (2026-11-07)

- **Delivered.** Domain `Community` (create/reconstitute/update/touch/requireActive), `Membership` (half-open
  `covers`, `end`), `Leadership` (appoint/end), `CommunityRules`, `CommunityException` (+ `clash()` for integrity
  violations), `Repository\CommunityRepository`. Application `CommunityAccess`, `CommunityHash`, `RequestReplay`;
  contracts `CommunityReader`, `CommunityRequests`, `CommunityAuditWriter`, published `CommunityParticipation`; read
  models. Commands `CreateCommunity`, `UpdateCommunity`, `JoinCommunity`, `LeaveCommunity`, `AppointCommunityLeader`,
  `EndCommunityLeadership`; queries `ListCommunities`, `GetCommunity`, `ListCommunityMembers`,
  `ListCommunityLeadership`, `GetCommunityHistory`, `ListPersonCommunities`. `DbalCommunityRepository`,
  `DbalCommunityReader` (+ `CommunityParticipation`), `DbalCommunityRequests`, `DbalCommunityAuditWriter`.
  `CommunityDirectoryController`, `CommunitiesJson`. Migration V36 (trigram index on `communities.name`).
- **The draft found in the tree was reworked**: create had no request replay or hash, several messages differed from
  Java, aggregates had public constructors, and raw SQL was used where the query builder works. `GroupMeeting` was
  removed until S3.
- **Tests.** `CommunityDirectoryApiTest` (directory, membership, leadership parts of `CommunityApiIntegrationTest` plus
  replay, permission and input rules), `MembershipTest` (port + rules), `CommunityHashTest`. `make check` green
  (1550 tests). Route diff: 12/31.
- **Deviations.** (1) `expectedVersion` is required in every body (AGENTS.md); Java's primitive `long` defaulted it to
  0. (2) Java's `concurrentJoiningCannotDuplicateMembershipOrAudit` runs two threads; here it is the sequential repeat
  plus the person-then-community lock order; S6 added the two-session test. (3) `CommunityHash` vectors came from an
  independent implementation; S6 replaced them with Java-captured ones.
- **Next session notes.** See the S2 log.

### S2 (2026-11-07)

- **Delivered.** Domain `FollowUp` (open/reconstitute/complete; OPEN then DONE once). Repository
  `findFollowUpUnlocked`/`insertFollowUp`/`saveFollowUp` (version in the WHERE). Commands `CreateCommunityFollowUp`
  (hash `FOLLOWUP` over the stripped note and ISO due date; replay mismatch is `RequestReplay::ALREADY_USED`),
  `CompleteCommunityFollowUp`; query `ListCommunityFollowUps` (OPEN by due date, all with `completed=true`).
  `CommunityFollowUpsController` (`#[IsGranted('ministries.manage|ministries.lead')]`, as Java's class guard).
  Routes `POST /api/communities/{communityId}/follow-ups`, `POST /api/communities/follow-ups/{followUpId}/complete`,
  `GET /api/communities/{communityId}/follow-ups`.
- **Rules (as Java).** Both commands: `CommunityAccess::actor()` first, then person (create only) and community locks,
  then `actor()` + `require()` (leaders of this community may act). Create needs an available person (else 404) who is
  a current member of an active community (else 409). Complete checks the version, then that it is still open.
- **Tests.** `CommunityFollowUpsApiTest` (port of `followupsAreScopedVersionedAndKeepCompletionHistory` plus order,
  replay, membership, leader scope, reader refusal and input rules), `FollowUpTest`. Shared helpers moved to
  `CommunitiesApiTestCase`. `make check` green (1556 tests). Route diff: 15/31.
- **Next session notes (S3, meetings).** Read `GroupMeetings` (preview, schedule, prepare, guest, attendance, finish),
  `CommunityViews.meetings/roster`, `CommunityMeetingsApi`/`Controller`, `CommunityOccurrenceMovement`,
  `CommunityScheduleListener`, and the meeting tests in `CommunityApiIntegrationTest`
  (`meetingPreparationFreezes...`, `correctionsRequireVersions...`, `frozenGroupRostersPreventEventRescheduling...`,
  `closingKeepsUnrecordedPeople...`). Events already publishes `CommunityCalendar` (preview/create/lock/get),
  `CommunityScheduleReader`, `OccurrenceMovementPolicy` and `OccurrenceScheduleListener` (E9); implement the last two
  in `Communities\Infrastructure` (tagged automatically by `_instanceof`). Re-add `GroupMeeting` as an aggregate with a
  private constructor. Lock order (Java): the Events occurrence (`CommunityCalendar::lock`), then the community, then
  the meeting; a person before those for guests and attendance. Roster cap 5,000; preparation from two hours before.

### S3 (2026-11-07)

- **Delivered.** Domain `GroupMeeting` (schedule/reconstitute, `freeze`, `guestAdded`, `finish`, `requireOpen`),
  `RosterEntry` (member/guest/reconstitute, `record`, `requireStatus`), `Repository\MeetingRepository`;
  `CommunityRepository::membershipsAt`; `CommunityException::refused`. Application `MeetingCalendar`, `MeetingLocks`,
  `LockedMeeting`; contract `MeetingReader` with read models `MeetingView`, `RosterEntryView`, `AttendanceReport`,
  `MeetingSummary`, `MeetingPage`, `RosterPage`. Commands `ScheduleGroupMeetings` (hash `SCHEDULE` over the schedule's
  Java record text and review hash; replay returns the first meeting), `PrepareGroupRoster`, `AddGroupGuest`,
  `RecordGroupAttendance`, `FinishGroupMeeting`; queries `PreviewGroupSchedule`, `ListGroupMeetings`,
  `GetGroupRoster`. `DbalMeetingRepository` (roster insert `ON CONFLICT (meeting_id, person_id) DO NOTHING`),
  `DbalMeetingReader`. Infrastructure `CommunityOccurrenceMovement` (Events `OccurrenceMovementPolicy`: a prepared
  roster vetoes rescheduling) and `CommunityScheduleListener` (`OccurrenceScheduleListener`: keeps `scheduled_at` in
  step, version unchanged), both tagged by `_instanceof`. `CommunityMeetingsController`
  (`#[IsGranted('ministries.read|ministries.manage|ministries.lead')]`). Events contract: `CommunitySchedule::of()` and
  `javaString()`.
- **Rules (as Java).** Schedule: managers only; Events also needs `events.manage` for preview (caller) and create
  (actor); an active GROUP; the community version moves. Prepare from two hours before the start: members as of the
  start time (cap 5,000), once; preparing again only audits. Guests need a frozen roster, stay off the membership and are
  listed once; the meeting version moves. Attendance: the first recording (row version 0) needs an open meeting, a
  SCHEDULED occurrence and an active community, from two hours before; corrections later are always allowed; an
  unchanged status writes nothing. Finish: CANCELLED is managers only, CLOSED needs the occurrence to have ended and a
  frozen roster. Leaders act only in communities they currently lead; `ministries.read` gets 403.
- **Tests.** `CommunityMeetingsApiTest` (ports of the four Java meeting tests, plus scheduling and replay, the Events
  reschedule following a meeting, preparation window, guests, leader scope and malformed input), `MeetingHandlersTest`
  (lock order with recording fakes, permission first, stale version, audit only on success), `GroupMeetingTest`,
  `RosterEntryTest`, `Events\CommunityScheduleTest`. `make check` green (1581 tests). Route diff: 23/31.
- **Deviations.** (1) Every schedule field is required (400 when missing), as in the Events schedule body of this port;
  Java's primitives defaulted to 0. (2) `expectedVersion` is required in every body (AGENTS.md). (3) The `SCHEDULE`
  hash had no Java-captured vector until S6.
- **Next session notes (S4, notices).** Read `CommunityMessaging` (contacts, review, approve, deliveries),
  `CommunityMessagingController`/`Api`, `CommunityDeliveryEligibility`, `CommunityTemplatePolicy`,
  `ApproveCommunityNoticeHandler`, `ReviewCommunityNoticeHandler`, `ListCommunityContacts`/`Deliveries` and
  `reviewedNoticesAreFrozenAndMembershipRevocationBlocksDispatch`. Table `community_notices` (V18); template
  `communities.notice` is seeded by V18. Follow the Children messaging pattern for `EmailDeliveries` and the two tagged
  policies; keep `CommunicationsIndependenceTest` green.

### S4 (2026-11-07)

- **Delivered.** Domain `CommunityNotice` (`approve`; evidence hidden from `__debugInfo`) and
  `Repository\NoticeRepository`. Application `NoticeReview` (Java `CommunityMessaging` contacts/review and the static
  `hash`), contracts `NoticeReader`, `NoticeView`, `ReviewedNotice`; `CommunityReader::membership` and
  `MembershipView::covers`; `CommunityAccess::SEND`. Command `ApproveCommunityNotice`; queries `ListCommunityContacts`,
  `ReviewCommunityNotice`, `ListCommunityDeliveries`. `DbalNoticeStore` (`NoticeReader` + `NoticeRepository`).
  Infrastructure `CommunityDeliveryEligibility` (source `communities`) and `CommunityTemplatePolicy`, tagged by
  `_instanceof`. `CommunityMessagingController` (`#[IsGranted('ministries.communications.send')]`): routes
  `GET /api/communities/memberships/{membershipId}/contacts`, `POST .../notice-review`, `POST .../notices`,
  `GET /api/communities/{communityId}/deliveries`.
- **Rules (as Java).** Every route needs `ministries.communications.send`; contacts and review also need directory
  scope, then a current membership of an active community with an available person (404/409), then `require()` on the
  community (managers anywhere, leaders where they currently lead). Review renders `communities.notice` with name,
  community and the stripped message (1-5000), and hashes membership id/version, community id/version, contact,
  recipient, subject, html and template version. Approve: actor permission, `actor()`, evidence (1-500), request hash
  `CommunityHash(actor, membership, contact, message, reviewHash, evidence)`; then the person lock, the community
  lock, `require()`; a known request id replays its delivery or is a 409; the review is redone under the locks and must
  match; the notice row, a QUEUED `text/html` delivery and a `NOTICE_APPROVED` history row (target = request id) are
  written in the command transaction. Eligibility rechecks approver permission (manage, or lead and currently leading),
  the notice/delivery binding, active community, covering membership, available person, the address and the hash.
  Deliveries list summaries only, newest notice first.
- **Tests.** `CommunityMessagingApiTest` (port of `reviewedNoticesAreFrozenAndMembershipRevocationBlocksDispatch`, plus
  eligibility rechecks, re-review after a change, request reuse, wrong address, leader scope, 401/403 and malformed
  input), `NoticeHandlersTest` (lock order person then community, replay, changed template revision, permission first;
  nothing queued or audited on failure), `CommunityTemplatePolicyTest`. `make check` green (1596 tests). Route diff: 27/31.
- **Deviations.** (1) `requestId` is required (400); Java accepted a null id and failed on insert. (2) Hashes are
  compared with `hash_equals`. (3) Java's `Review.toString` (hash only) becomes `ReviewedNotice::__debugInfo`.
  (4) Java's controller sets no cache headers, so these routes keep the default `no-cache, no-store` trio.
- **Next session notes (S5, member views).** Read `MemberCommunityViews`, `MemberCommunityReader`,
  `JooqMemberCommunityStore`, `MemberCommunitiesController`/`Api` and their tests (`listmycommunities`,
  `getmycommunity`, `listmygroupmeetings`, `getmygroupmeeting`). The caller's person comes from IdentityAccess
  `MemberIdentity`.

### S5 (2026-11-07)

- **Delivered.** Contract `MemberCommunityReader` with read models `MemberCommunity`, `LeaderEntry`,
  `MemberMeetingRow`, and the response models `MemberCommunityPage`, `MemberCommunityDetails`,
  `MemberCommunityLeader`, `MemberGroupMeeting`, `MemberGroupMeetingPage`. Application `MemberCommunityViews`
  (leader names through People, meeting dates through Events `CommunityScheduleReader`). Queries `ListMyCommunities`,
  `GetMyCommunity`, `ListMyGroupMeetings`, `GetMyGroupMeeting`. `DbalMemberCommunityReader`.
  `MemberCommunitiesController`: `GET /api/member/communities[/{id}]`, `GET /api/member/group-meetings[/{id}]`,
  all `Cache-Control: no-store` as Java.
- **Rules (as Java).** The caller's person comes from IdentityAccess `MemberIdentity::currentPerson()`; no staff
  permission is used. Lists return `verified: false` with no items without a verified person; detail reads are 404.
  Every read needs the person's current (half-open) membership of an active community; the role is the current
  leadership role or MEMBER; the parent name shows only an active parent and grants nothing. Leaders: current
  appointments on current memberships, by role then id, named only when the person is available (filtered after
  paging, as Java), `hasMoreLeaders` from a limit + 1 read. Meetings: next 180 days, by time then id; a meeting
  scheduled before the membership began is hidden unless the person has a roster row; attendance is the roster status,
  else NOT_LISTED (frozen roster) or NOT_PREPARED; status is CANCELLED when the Events occurrence is. A missing Events
  date is a 409. A `communityId` filter must be an own community (404). Reads never create roster rows.
- **Tests.** `MemberCommunitiesApiTest` (port of the six `MemberCommunitiesApiIntegrationTest` tests, plus leader
  paging and own role, the 180-day window and meeting paging, and malformed query input). The meeting helpers moved to
  the `GroupMeetingFixtures` trait shared with `CommunityMeetingsApiTest`. `make check` green (1604 tests). Route diff: 31/31.
- **Deviations.** (1) Symfony sends `no-store, private` (as in Communications A2). (2) A malformed `communityId` query
  value is a 400, as Java's `UUID` binding.
- **Next session notes (S6, hardening).** Two-session tests with `tests/Giving/Infrastructure/ParallelSession` for
  parallel joins (person then community lock) and concurrent attendance on one roster row; Java-captured vectors for
  `CommunityHash` (request, `FOLLOWUP`, `SCHEDULE`, notice and review hashes); route parity sweep against Java; README
  and AGENTS.

### S6 (2026-11-07)

- **Java-captured hash vectors.** `docs/porting/vectors/CommunityHashVectors.java` calls Java's compiled
  `CommunityHash.of` and `CommunityMessaging.hash` (`backend-java/target/classes`, JDK 25) with the real argument
  types: UUIDs, `long` versions, `LocalDate`, the `CommunityCalendar.Schedule` record (including a fractional
  `LocalTime`), nulls and non-ASCII text. `CommunityHashTest` now asserts those twelve values for the `CREATE`, `JOIN`,
  `APPOINT`, `FOLLOWUP` and `SCHEDULE` request hashes, the notice review hash and the approval request hash, and builds
  the inputs through the production paths (`CommunitySchedule::of()->javaString()`, `NoticeReview::hash`). All matched
  without code changes.
- **Two-session tests.** `CommunityConcurrencyTest` (ports Java `concurrentJoiningCannotDuplicateMembershipOrAudit`).
  This session sends a request with its transaction left open. A forked `ParallelSession` boots its own kernel with
  `Connection` replaced by the forked connection, sends the competing request over HTTP, and must be seen waiting on a
  lock in `pg_stat_activity`. Cases: two joins of one person (201 and 409, one membership, one audit); a retry of one
  join request (both 201 with the same id, one request row); two first recordings on one roster row (204 and a stale
  409, one audit); a join waiting on roster preparation (the join succeeds and stays off the frozen roster).
- **Route parity.** The `@*Mapping` routes in Java `communities` match the Communities routes from
  `debug:router`: 31/31, identical methods and paths.
- **Docs.** README context table, AGENTS (vectors and the two-session pattern), `remaining-work.md` status board.
- **Tests.** `make check` green (1612 tests).
- **Still open (cross-cutting, not Communities-specific).** The Java-captured cipher vector for push (A2), and the
  Giving and Events hash vectors. The same generator approach works for them now that a JDK
  (`~/.jdks/azul-25.0.5`) is available locally.
