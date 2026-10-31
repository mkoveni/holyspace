# Group meetings and leader workspace

**Group workspace** brings ministry/small-group rosters, linked meetings and attendance into one screen. Ministries owns group membership and meeting links. Events owns each gathering and occurrence. Attendance owns participation records and correction history.

## Administrator setup

1. Add the leader and members to People.
2. In **Ministries**, create the ministry or small group and add memberships with joining dates. Assign the leader's membership the **leader** role.
3. Create a login account with that leader's Person selected. The account's person link is used for scope; a username or a People relationship does not establish leadership. Unlinked accounts cannot use leader scope.
4. In **Roles**, create a leader role. Choose **Manage Permissions**, select the existing `ministries.lead` permission from the catalogue and add it. Assign the role to the account through User Accounts. Existing accounts can be looked up by account ID; account creation must include the person link for this scope.
5. Do not add church-wide `people.read`, `attendance.manage`, `ministries.manage` or account-administration permissions merely to enable the leader workspace.
6. Create the group's gathering in Events, then open **Group workspace**, select the group, and link the scheduled event occurrence. Managers need `events.read` to use the event selector. Each occurrence can belong to one group meeting; use separate occurrences for different groups.

`ministries.manage` grants the full workspace for all groups, including archived history. `ministries.lead` alone grants access only to active groups where the active account's linked person is a current active leader and their joining date has begun. Removing leadership, ending membership or archiving the group revokes subsequent scoped reads and writes. Disabled accounts are rejected. Changes are checked server-side on every request; cached menus do not grant authority.

Migration `Version20261012000000` adds the lead permission and assigns it to named administrator roles. Other roles need explicit assignment. Sign out and back in to refresh menus. Administrator role/permission catalogues are now available through authenticated `GET /api/roles` and `GET /api/permissions`, both requiring `identity.manage`; leaders cannot access them.

## Leader tasks

1. Sign in and open **Group workspace**. Only your authorised groups appear.
2. Select a group to view member names, membership roles and joining/leaving dates. Contact details, unrelated people and safeguarding records are not included.
3. Choose a **Group meeting** to see its roster and attendance counts.
4. Use **Record attendance** to select present, absent or excused for a roster member.
5. Use **Correct attendance** for an existing entry. A reason and its current version are required; previous values remain in Attendance history.
6. Reload after a stale-edit conflict and review the current record before making a new change.

Leaders cannot create/cancel meeting links, edit membership or access the global Attendance/Ministries APIs through this permission. Managers handle those tasks. The workspace delegates only roster-scoped attendance operations; it never grants global Attendance permission.

## Roster and cancellation rules

The meeting's local date is derived from its occurrence in the event timezone. Membership joining dates are inclusive; leaving dates are exclusive. A member can attend up to the day before their leaving date. Meeting rosters include eligible memberships and people whose attendance was recorded through this group workspace, preserving that recorded history if rescheduling changes membership eligibility.

Unrelated attendees already recorded against the same event are not shown to the group leader. A member's existing Attendance record is reused, not duplicated. A child's check-in contributes presence once and exposes only a presence flag; pickup, staffing and custody information remain in Children's Ministry. Attempts to mark that child absent/excused are rejected by Attendance.

Cancelling a group meeting closes new group attendance but does not cancel the Events occurrence or erase existing participation. Cancelling the occurrence also closes new group attendance. Corrections to existing entries remain possible for authorised staff. A local group cancellation remains cancelled if the event is restored. Archived groups are closed to new meetings/attendance; managers can view their history and correct existing entries, while leaders lose access.

Occurrence links cannot be reassigned or duplicated across groups, including after cancellation. Create a separate occurrence for a different group. Rescheduling keeps the same occurrence and Attendance identities; review the resulting roster and inform members separately. No messages are sent automatically.

## API and operations

Authenticated scoped endpoints:

- `GET /api/groups` — authorised groups only.
- `GET /api/groups/{id}` — minimal roster, meeting occurrences, eligible/recorded attendee rows, counts and manager capability; no-store.
- `POST /api/groups/{id}/meetings` — manager links `{occurrenceId}`.
- `POST /api/groups/{groupId}/meetings/{id}/cancel` — manager sends `{expectedVersion, reason}`.
- `POST /api/groups/{groupId}/meetings/{id}/attendance` — `{personId, status, expectedVersion?, reason?}`. Existing attendance requires its current version and a correction reason; a new entry must omit/null the version.

Meeting links use the existing `ministries_records` table under `meetings`; they are versioned and audited. Attendance and meeting-history writes share one workflow lock and transaction. Unrelated groups/people are denied before mutations; cross-group meeting IDs do not grant access. The previously disabled global Attendance permission attribute has been restored to enforce the boundary.

Public group directories, join requests, group communications, leader contact-detail permissions, collection pagination, bulk attendance entry, recurring group-meeting generation and trends remain extensions. This workspace is for one church and uses the existing single-host workflow lock design.
