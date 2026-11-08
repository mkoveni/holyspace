# Porting Volunteering to Symfony

Status: **complete 2026-11-07: V1-V8 done, 41 of Java's 41 routes.** Java
(`backend-java/src/main/java/org/onechurch/volunteering`) is the behavioural and API source of truth; the applied V14
migration defines the schema. Update the status board and the log at the end of every session.

## Session prompt

> Continue the Volunteering port: read `backend-symfony/AGENTS.md` and this file, then do the next unchecked slice.
> Java is the specification. Finish with `make check` green and update this file.

## Scope

Teams, roles, memberships and role approvals; volunteer profiles, credential types and credentials; availability; shifts
(single, bulk, preview); assignments and their status events; invitations and the member volunteering views; reminders
and deliveries. Preserve Java's routes, JSON (record field names, nulls included) and messages.

The readiness slice (P2a/P2b: `VolunteerReadiness`, `PublishedStaffingDirectory`, `ActivityRegistry`, `Fingerprint` and
the `TeamReader`/`VolunteerReader`/`RosterReader` contracts) was ported earlier for Events and Children Ministry. Those
contracts stay unchanged: new reads go through new contracts (for example `TeamDirectory`).

## Status board

| # | Slice | Java source | Routes | Needs | Status |
|---|---|---|---|---|---|
| V1 | Teams, roles, memberships | `TeamManagement` (create/update/role/updateRole/membership), `ServingViews.teams/team`, `VolunteerProfiles.lock/lockAll/lockTeamActivities`, `JooqTeamStore`, `JooqServingAudit`, `ServingTeamsController` (part), `ServingRolesController` | 7 | - | [x] |
| V2 | Role approvals and revocation | `TeamManagement.approval/revoke`, `ServingTeamsController.approve/revoke` | 2 | V1 | [x] |
| V3 | Volunteer profiles, credential types, credentials, account linking | `VolunteerProfiles`, `VolunteerProfilesController`, `VolunteerAccountController`, `JooqVolunteerStore` | M | V1 | [x] |
| V4 | Availability | `VolunteerProfiles.availability`, `SetVolunteerAvailability` | S | V3 | [x] |
| V5 | Shifts, bulk creation and preview | `RosterPlanning` (shifts), `ScheduleDates`, `VolunteerSchedulePreviewController` | L | V1 | [x] |
| V6 | Assignments and status events | `RosterPlanning` (assignments), `VolunteerRosterController` | M | V5 | [x] |
| V7 | Invitations and member volunteering | `VolunteerInvitationsController`, `MemberVolunteerActions`, `MemberVolunteerViews` | M | V6 | [x] |
| V8 | Reminders, deliveries, hardening | `ReminderReview`, `VolunteerRemindersController`, `VolunteerReminderEligibility`, `VolunteerTemplatePolicy` | M | V6 | [x] |

## Decision log

- Volunteering is a flat context at `src/Volunteering/`.
- Lock order (Java `VolunteerProfiles.lockAll`): people sorted by person id (People `PersonDirectory::lock`), then the
  linked activities of the volunteers' non-cancelled assignments, sorted by kind then id and passed to every tagged
  `ServingActivityLocks` (each locks only its own kind), then the volunteer rows, then the team row. A team or role
  change locks the team's linked activities before the team row. `VolunteerLocks` and `TeamLocks` hold this order;
  `TeamHandlersTest` asserts it.
- `ServingActivityLocks` implementations are tagged `volunteering.activity_locks` and injected into `VolunteerLocks`.
  Children Ministry's `ServingActivityGuard` is the first one; V1 is the first caller.
- The team row serializes its roles, memberships and approvals: every such change moves the team version
  (`Team::touch`), so a second edit from the same screen is a 409.
- Writes that hit a unique index (`serving_roles (team_id, name)`, later `serving_volunteers.person_id` and request ids)
  become `VolunteerException::clash()`, Java's `DuplicateKeyException` handler message.
- Planning lock order (Java `RosterPlanning`): people sorted by person id, then linked activities, then volunteer rows
  (`VolunteerLocks::lockAll`), then either the team rows sorted by id (shift creation, bulk) or the shift row, then
  assignment rows, then their invitation rows (every booking change). `RosterPlanning::lock` holds volunteer, shift and
  assignment for one assignment; reminder approval uses it too. `RosterHandlersTest` asserts the order.
- Readiness keeps its narrow contracts (`VolunteerReader`, `TeamReader`, `RosterReader`); the planning and view reads go
  through `VolunteerDirectory` and `RosterDirectory`, which extend them. `ShiftView`, `AssignmentView`, `VolunteerView`,
  `CredentialTypeView` and `CredentialView` gained trailing fields with defaults, so the staffing contracts used by Events
  and Children Ministry are unchanged.
- Duty notices go through Volunteering's own `DutyNotices` contract; `Infrastructure\InboxDutyNotices` adapts it to
  Communications `MemberNotifications` inside the planning transaction (kinds `DUTY_ASSIGNED`, `DUTY_CHANGED`,
  `VOLUNTEER_INVITATION`, source keys as Java).
- Read models for the team views (`TeamRecord`, `ServingRoleRecord`, `ServingPeriod`, `TeamPage`, `TeamDetails`)
  serialize as Java's domain records (`Team`, `ServingRole`, `TeamMembership`, `RoleApproval`).

## Log

### V1 (2026-11-07)

- **Delivered.** Domain `Team`, `ServingRole`, `TeamMembership`, `Volunteer` (reconstitute only until V3), repositories
  `TeamRepository` and `VolunteerRepository` (`lock`); `ServingRules::text/version`; `VolunteerException::clash`.
  Application `VolunteerLocks`, `TeamLocks`, `CredentialCatalogue`, `ServingViews::page`, `ServingPermissions`;
  contracts `TeamDirectory`, `LinkedActivities`, `ServingAudit` and the read models. Commands `CreateServingTeam`,
  `UpdateServingTeam`, `CreateServingRole`, `UpdateServingRole`, `JoinServingTeam`; queries `ListServingTeams`,
  `GetServingTeam`. `DbalTeamStore` (`TeamRepository` + `TeamDirectory`), `DbalVolunteerRepository`,
  `DbalLinkedActivities`, `DbalServingAudit`. `ServingTeamsController`: `POST|GET /api/volunteering/teams`,
  `PUT|GET /api/volunteering/teams/{id}`, `POST .../{id}/roles`, `PUT .../{teamId}/roles/{roleId}`,
  `POST .../{id}/memberships`.
- **Rules (as Java).** Commands need `volunteering.manage` for the actor (checked first); reads need
  `volunteering.read`. Team names 1-150, role names 1-100 characters, stripped. Role creation: requirements required,
  each must be a catalogue type (404 `Credential type`), at most 30 unique; the children responsibility is NONE, LEADER or
  ASSISTANT. Role edit: the role must belong to the team (404 `Team role`), the children responsibility cannot change
  (409), unknown types are a 400. Membership: the team must be active (409), the period must end after it starts and
  overlap none of the volunteer's unrevoked memberships of the team (half-open; adjacent periods are allowed). Audit
  actions `team.created`, `team.changed`, `team.role.created`, `team.role.changed`, `team.member.added`. Lists page
  1-100 with offset 0-100000; teams by name then id, roles by name then id (at most 100), memberships and approvals newest
  first (at most 1000).
- **Tests.** `ServingTeamsApiTest` (port of `protectedEndpointsRejectAnonymousAndReadOnlyCoordinators`, plus versions,
  requirements, children responsibility, duplicate names, overlap, inactive teams, JSON shape, paging and malformed
  input), `TeamHandlersTest` (lock order with recording fakes, permission first, stale version and refused changes write
  nothing, audit only on success), `TeamTest`, `DbalLinkedActivitiesTest`. `make check` green (1636 tests).
  Route diff: 7/41.
- **Deviations.** (1) `expectedVersion` is required in every body (AGENTS.md); Java's primitive `long` defaulted it to
  0. (2) A role's `requiredCredentials` are listed by type id; Java read them in table order. (3) Text length counts
  code points (as in Communities); Java counted UTF-16 units. (4) Volunteer profiles and credential types have no write
  path until V3, so the API tests seed them in SQL.
- **Next session notes (V2).** Read `TeamManagement.approval/revoke`, `ApproveServingRoleHandler`,
  `RevokeServingApprovalHandler` and the `Approval`/`Revoke` records in `VolunteeringApi`. Routes
  `POST /api/volunteering/teams/{id}/approvals` and `POST /api/volunteering/teams/{id}/revoke` (204; `roleApproval`
  chooses an approval or a membership). Both lock the volunteer through `VolunteerLocks::lock`, then the team at the
  expected version; an approval needs a role of that team (404 `Team role`) and must not overlap an unrevoked approval of
  that role; revoking needs the record on that team (404 `Role approval` / `Membership`) and sets `revoked_at` only when
  still null. Audit `team.role.approved`, `team.approval.revoked`, `team.membership.revoked`. `DbalTeamStore` already
  reads approvals; add `approvalWindows`, `addApproval`, `revokeMembership`, `revokeApproval` to `TeamRepository`.

### V2 (2026-11-07)

- **Delivered.** Domain `RoleApproval` (`grant`, `window`, `overlapsAny`); the half-open overlap check moved to
  `ValidityWindow::overlapsAny`, which `TeamMembership` now delegates to. `TeamRepository` gained `approvalWindows`,
  `addApproval`, `hasApproval`, `hasMembership`, `revokeApproval`, `revokeMembership` (in `DbalTeamStore`). Commands
  `ApproveServingRole`, `RevokeServingApproval`. Routes `POST /api/volunteering/teams/{id}/approvals` (201 `{id}`) and
  `POST /api/volunteering/teams/{id}/revoke` (204).
- **Rules (as Java).** `volunteering.manage` first; lock the volunteer (`VolunteerLocks::lock`), then the team at the
  expected version. An approval needs a role of that team (404 `Team role`; like Java, neither the team nor the role
  has to be active) and must not overlap an unrevoked approval of that role for the volunteer (409). Revoking needs the
  record on that team for that volunteer (404 `Role approval` / `Membership`; an approval belongs to the team through
  its role) and sets `revoked_at` only while it is null, so revoking twice keeps the first instant but still moves the
  team version and writes an audit row, as Java does. Audit `team.role.approved`, `team.approval.revoked`,
  `team.membership.revoked`.
- **Tests.** `ServingTeamsApiTest` (approval overlap, adjacency, foreign role, unknown volunteer, stale version, empty
  period, JSON shape; revocation of each kind, wrong kind or team is 404, repeat revocation keeps the instant, a revoked
  record no longer blocks its period; 401/403; malformed bodies), `TeamHandlersTest` (lock order, permission before any
  lock, stale version and refusals write nothing, audit per kind), `TeamTest` (`RoleApproval`). Route diff: 9/41.
- **Deviations.** (1) A missing `roleApproval` means a membership (Java's primitive `boolean`); a non-boolean value is a
  400. (2) The record lookup for revocation is one query on the team's roles instead of Java's per-role scan (Java capped
  it at 100 roles and 1000 approvals each); the result is the same within those caps.
- **Next session notes (V3).** Read `VolunteerProfiles.register/account/status/type/credential/revoke`,
  `VolunteerProfilesController`, `VolunteerAccountController`, `JooqVolunteerStore` and the matching handlers. Routes
  `POST|GET /api/volunteering/volunteers`, `GET /api/volunteering/volunteers/{id}`, `PUT .../{id}/status`,
  `PUT .../{id}/account`, `POST|GET /api/volunteering/credential-types`, `POST .../{id}/credentials` and
  `POST .../{id}/credentials/revoke` (availability is V4). `Volunteer` needs its write factories; the API tests can then
  stop seeding volunteers and credential types in SQL. Credentials reuse `ValidityWindow` and the readiness code; keep
  the P2a/P2b contracts unchanged.

### V3-V8 (2026-11-07)

- **Delivered (V3, profiles).** Domain `Volunteer` (`register`, `change`, `link`, `availability`, `touch`), `CredentialType`,
  `Credential`, a widened `VolunteerRepository`. Commands `RegisterVolunteer`, `ChangeVolunteerStatus`,
  `LinkVolunteerAccount`, `CreateCredentialType`, `RecordVolunteerCredential`, `RevokeVolunteerCredential`; queries
  `ListVolunteers`, `GetVolunteer`, `ListCredentialTypes`. `VolunteerProfilesController`: `POST|GET
  /api/volunteering/volunteers`, `GET .../{id}`, `PUT .../{id}/status`, `PUT .../{id}/account`, `POST|GET
  /api/volunteering/credential-types`, `POST .../{id}/credentials`, `POST .../{id}/credentials/revoke`.
- **Delivered (V4, availability).** `VolunteerAvailability` (shared by staff and member), `SetVolunteerAvailability`,
  `PUT /api/volunteering/volunteers/{id}/availability`. A replacement stores a new revision; old rows stay as history.
  `Domain\ZonedTime` (extracted from `Availability`) resolves local times and refuses DST gaps and overlaps.
- **Delivered (V5, shifts).** Domain `Shift`; `RosterPlanning::create/bulk/update`, `ScheduleDates` (Java
  `UUID.nameUUIDFromBytes` request ids), `CreateVolunteerShift`, `BulkCreateVolunteerShifts`, `UpdateVolunteerShift`,
  `PreviewVolunteerSchedule`, `ListVolunteerShifts`, `GetVolunteerShift`, `GetVolunteerReadiness`,
  `ListVolunteerActivities`. Routes `POST /api/volunteering/shifts`, `POST .../shifts/bulk`, `POST .../shifts/preview`,
  `PUT|GET .../shifts/{id}`, `GET .../shifts`, `GET .../readiness`, `GET .../activities`.
- **Delivered (V6, assignments).** Domain `Assignment`; `RosterPlanning::assign/change`, `AssignVolunteer`,
  `ChangeVolunteerAssignment`, `GetAssignmentHistory`. Routes `POST /api/volunteering/assignments`, `POST
  .../assignments/{id}/status`, `GET .../assignments/{id}/events`.
- **Delivered (V7, invitations and member volunteering).** Domain `Invitation`; `RosterPlanning::invite/respond/expire`,
  `RosterViews` (effective status WITHDRAWN/EXPIRED shown, never written), `MemberVolunteerViews`, `OwnVolunteer`
  (Java `MemberVolunteerActions.own/recheck`). Commands `InviteVolunteer`, `RespondVolunteerInvitation`,
  `ExpireVolunteerInvitations`, `RespondMyVolunteerInvitation`, `UpdateMyVolunteerAvailability`; queries
  `GetOwnVolunteerSchedule`, `GetMyVolunteering`. Routes `POST /api/volunteering/assignments/{id}/invitations`, `POST
  .../invitations/{id}/respond`, `POST .../invitations/expire`, `GET .../me/invitations`, `GET /api/member/volunteering`,
  `POST /api/member/volunteering/invitations/{id}/respond`, `PUT /api/member/volunteering/availability` (member routes
  `no-store`). Duty notices reach the member inbox (`InboxDutyNotices`).
- **Delivered (V8, reminders).** Domain `VolunteerReminder`, `ReminderRepository`; `ReminderReview` (template
  `volunteering.shift-reminder`, Java hash), `ApproveVolunteerReminder`, `ReviewVolunteerReminder`,
  `ListReminderContacts`, `ListVolunteerDeliveries`; `Infrastructure\VolunteerReminderEligibility` and
  `VolunteerTemplatePolicy` (tagged for Communications). Routes `GET /api/volunteering/assignments/{id}/reminder-contacts`,
  `POST .../reminder-review`, `POST .../reminders` (200, the delivery id as a JSON string), `GET
  /api/volunteering/deliveries`.
- **Stores.** `DbalVolunteerReader` (now `VolunteerDirectory`), `DbalVolunteerRepository` (writes), `DbalRosterReader`
  (now `RosterDirectory`), `DbalRosterStore` (`RosterRepository`), `DbalReminderStore`, `DbalTeamStore::role`. Every save
  names the previous version; unique violations (`person_id`, `account_id`, credential `code`, request ids, one live
  assignment per shift and volunteer, one pending invitation per assignment) become `VolunteerException::clash()`.
- **Tests.** `VolunteerRosterApiTest` (port of `VolunteeringApiIntegrationTest`, all ten tests, plus cancellation,
  status history, capacity, shift rules and JSON shape, readiness and activities, invitation expiry rules),
  `MemberVolunteeringApiTest` (port of `MemberVolunteeringApiIntegrationTest`, all eight, plus malformed availability),
  `VolunteerRemindersApiTest` (port of `VolunteerRemindersApiIntegrationTest`, plus retries, hash and contact rules,
  permissions, template policy), `VolunteerProfilesApiTest` (registration, list/search and live access as in
  `ListVolunteersHandlerIntegrationTest`, status and account links, catalogue, credentials, availability JSON),
  `RosterHandlersTest` (lock order, permission before any lock, stale version writes nothing, invitations withdrawn),
  `ScheduleDatesTest` (port of `PreviewVolunteerScheduleHandlerTest` plus a `nameUUIDFromBytes` vector), `RosterTest`
  (aggregates). The shared fixture is `VolunteeringApiTestCase` (Java `VolunteerIntegrationTestSupport`). Route diff:
  41/41.
- **Deviations.** (1) `expectedVersion` is required in every body (AGENTS.md). (2) Availability rules and windows are
  built from the request before the volunteer is locked, so a request that is both stale and malformed is a 400; Java
  checked the version first (409). (3) `invitations/expire` runs its batch (at most 100) in one transaction; Java used
  one transaction per invitation, so here one failure rolls back the batch. (4) A shift update locks each live
  assignment row (after the shift) before changing it; Java saved without that lock. (5) An empty `sourceKind` is
  refused as "Choose a valid activity source."; Java reached the registry's "source is not available" (also 400). (6)
  Java's two-thread `concurrentOverlappingDutiesCannotDoubleBookOneVolunteer` is not ported: `RosterHandlersTest` proves
  the person lock comes first, which serializes the two bookings. (7) Member routes send `Cache-Control: no-store,
  private` (Symfony appends `private`). (8) The member `respond` decision is `NotBlank` (Java `NotNull`); blank is a 400
  either way. (9) `ServingTeamsApiTest` still seeds volunteers and credential types in SQL.
- **Follow-ups.** A two-session (`ParallelSession`) test for concurrent bookings of one volunteer; Java-captured
  `Fingerprint` vectors for shift and assignment request hashes (the cross-cutting vectors item in
  `remaining-work.md`).

