# Reports, segments and follow-up tasks

Use **Reports and segments** to understand recorded activity and save reusable People filters. Use **Follow-up tasks** to assign work and record an outcome. Automation creates tasks; it never contacts people or sends notifications.

## Access and setup

Apply migration Version20261012050000 and sign in again to refresh permissions. It creates reporting storage and grants the new permissions to named administrator roles only.

- **reports.read:** view permitted report sections, saved segments and CSV exports.
- **reports.manage:** create/edit segments and configure automation; it also grants reports.read.
- **followups.work:** read and update tasks explicitly assigned to your account.
- **followups.manage:** view and manage permitted tasks, assign staff, and configure automation; it also grants followups.work.

Source permissions still apply. People filters require people.read; attendance conditions require attendance.manage; ministry membership filters require ministries.manage. Giving totals require giving.manage. A report permission does not grant access to those source contexts. Scoped group leadership or member permissions do not grant global reporting.

An assignee must have an active account, followups.work (or followups.manage), and every source permission required by the task. Assignment never grants permissions. The staff selector lists eligible accounts by username, not a general member directory.

## Run an operational report

1. Open **Reports and segments**.
2. Choose **Report from** and **Report to**, an ordered period of at most 367 days.
3. Select **Run report**.
4. Select **Export report CSV** when needed.

Only sections permitted by your role appear. Membership counts are current totals, not historical snapshots for the selected period. Attendance and giving use the selected dates.

Attendance combines named entries and Children's check-ins. The same person in the same occurrence is counted once; a recorded Children's check-in represents presence even after collection. Distinct people across the period are counted separately from occurrence participation. Date rows can include services and group meetings; they are not limited to worship services. Cancelled occurrences are excluded. Corrections and rescheduling affect the live report, so it is not a historical audit snapshot.

Visitor counts remain separate unnamed counts. They cannot establish which visitor attended or resolve overlap caused by incorrect staff entry.

Giving uses existing gift/reversal dates and separate currency totals. It does not combine currencies or infer a converted total. Reports do not include bank details, Children support notes, passwords or safeguarding cases.

## Save a People segment

1. Select **Create segment** and enter a meaningful name.
2. Choose membership status, adult/child age group, active ministry membership, and/or an attendance condition.
3. Choose a rolling lookback of 1–366 days (including today), or a fixed date period, when filtering attendance.
4. Select **Preview matches**, review the count, and select **Save segment**.
5. Select **View people** to inspect the live matches or **Export segment CSV**.

Filters are combined with AND. Adults are at least 18 on the evaluation date; unknown or future birth dates do not match an age group. Archived ministries match no active memberships.

**Recorded present** and **Recorded absent** require recorded evidence in the period. **No recorded presence** means no positive presence was recorded; it does not prove absence or establish missed consecutive services. An unrecorded person may match it. Use an explicit membership/ministry filter to define the intended population, and verify records before outreach.

Dates and daily evaluation boundaries use UTC. Segments are saved definitions, not frozen membership lists. CSV text is escaped to prevent user-entered names being interpreted as spreadsheet formulas.

Editing a segment requires its current version and pauses any enabled automation using an older definition. Another user's change requires a reload rather than overwriting it.

## Assign a follow-up

From a segment's **View people** result, select **Create follow-up** next to a person. Enter a short title, choose an eligible accountable staff account, and set a due date.

The server checks that the person still matches the segment at assignment time. The task keeps its original source-permission requirements; later segment edits do not rewrite existing tasks.

In **Follow-up tasks**, an operator sees only their assigned tasks for which they still have source access. Managers see all tasks their current source access permits. The page shows the assignee, due date, overdue status and history.

Select **Record progress or outcome** to move a task to open, in progress, completed or cancelled. An outcome/reason is required to complete, cancel or reopen a closed task. Keep notes brief; these are operational follow-ups, not a confidential case-management record. Task history records the staff account and time. Managers can reassign tasks and adjust the due date with a reason.

Concurrent task changes return a conflict and require a refresh. Removing a source permission hides affected tasks and prevents updates; assigning a task does not restore access.

## Enable task automation deliberately

1. Save and review a segment.
2. Select **Create automation rule**.
3. Choose the segment, eligible staff assignee, task title and days until due (0–90).
4. Save it disabled first.
5. Review it, then explicitly select **Enable daily task creation for this reviewed segment**.
6. Use **Evaluate now** for a deliberate immediate evaluation.

The production followups worker evaluates enabled rules at most once per UTC day. Each person gets at most one task per rule, including previously completed or cancelled tasks. Repeating evaluation or restarting workers does not create duplicate tasks. For a new outreach cycle, create and review a new rule.

The rule records the manager who created or last reviewed it as its owner. The owner must remain active and retain reports.manage, followups.manage and source access. The assignee must remain active and eligible. Changes to the segment or either person's required access pause the rule for review. A manager with current source access can choose an eligible assignment and explicitly reactivate it.

More than 1,000 matches pauses a rule rather than generating a large uncontrolled batch. Narrow the segment before reactivation. Already-created tasks remain accountable records; pausing a rule does not automatically complete or cancel them.

## Operations and communication

Start the **followups** service alongside the existing production workers. It uses the same shared workflow lock and database transactions. Its health check uses a worker heartbeat; the operations probe also reports rules needing review.

The development Compose file also includes the followups worker. Before starting it, create backend/var/locks writable by the PHP container user; PHP and the worker share this directory for workflow locking. Start both with docker compose up -d php followups. A worker is already running in the current local development environment.

For a manual invocation, run:

    docker compose exec -T php php bin/console app:followups:run

The command exits nonzero when it pauses a rule for review. Disabled rules are not evaluated. Unexpected database failures roll back work and fail the worker pass; its normal restart policy applies.

No task rule creates or queues a Communications campaign, email or SMS. Contacting a person is a separate, explicit staff decision through the existing Communications flow, which checks current channel consent and delivery configuration. There are no automatic communications or consent changes in this milestone.

## Pilot checklist

Use a staff account with explicit source permissions. Verify one segment with known matches and a report against the existing source screens. Check corrected attendance, child deduplication, visitors, reversals and separate currencies. Export a CSV.

Assign one task to a work-only staff account and another to a different account; verify direct-ID isolation, completion notes and stale-update rejection. Save a rule disabled, enable it deliberately, evaluate twice and confirm one task per person. Change its segment or revoke required access and confirm that the rule pauses and monitoring reports review work. Resolve the review and confirm monitoring recovers.

A live staff pilot and defining the church's outreach practices remain operational work. This milestone does not add arbitrary SQL queries, member directory sharing, missed-service inference, recurring outreach to the same person, automatic notifications or case management.
