# Volunteer responses and service planning

This milestone adds invitations, scoped volunteer responses, reminder drafts, and ordered service plans for one church. Volunteering owns eligibility, reserved positions and responses; Service Planning owns the order/resources; Events owns the scheduled occurrence; Communications owns consent and delivery.

## Prepare volunteer access

1. Add the volunteer to People and create a volunteer profile with approved roles, availability and any required screening/qualifications.
2. Create a login account with that Person selected. Assign a role containing `volunteering.serve`, using the existing permission catalogue.
3. Do not grant global `people.read`, `volunteering.manage`, `communications.manage` or service-plan read permissions just to let someone respond.
4. Coordinators use `volunteering.manage`. Plan editors use `service_planning.manage`; staff readers use `service_planning.read`. Management implies the corresponding read/serve capability, but personal serving still requires an active account linked to a person.

Migration `Version20261012010000` creates service-plan storage and the three new permissions and grants them to named administrator roles. Assign other roles explicitly and refresh the browser session after permission changes. Deploy backend, frontend and workers together: older backends do not understand the new `invited` assignment status.

## Invite and respond

1. Schedule a gathering in Events and prepare a shift in Volunteering. Its positions, role and times must be valid for that occurrence.
2. Open **Volunteer invitations**. Choose the shift, find the volunteer through backend autocomplete, and choose a response deadline. The deadline must be in the future and no later than shift start. The editor uses your browser timezone and sends an explicit UTC instant.
3. Choose **Create invitation**. Current approval, availability, screening, qualifications, capacity and overlap rules are checked. A pending invitation reserves a place and blocks overlapping assignments, but does not count as eligible children's staffing.
4. The volunteer signs in and opens **Volunteer workspace**. Only their assignments/invitations appear. They may accept or decline a pending invitation.
5. Acceptance rechecks current eligibility, shift state, capacity and conflicts. Acceptance makes the assignment `assigned`; decline releases its place. Versions protect against stale responses.

Expiry is enforced server-side during volunteering/planning reads and mutations, including legacy coordinator operations. Expired pending invitations cancel their reserved assignment and free the place. There is no automatic reassignment to another volunteer.

Changing shift details or rescheduling/cancelling an occurrence invalidates the old invitation. Review/reconfirm the shift, cancel the old assignment when appropriate, and create a fresh invitation deliberately. Restoring an event never silently accepts an invitation. Already accepted volunteers cannot use invitation response actions to cancel committed staffing; coordinators manage those changes through existing assignment controls.

The personal workspace includes historical assignments and terminal invitation responses, without other volunteers' profiles, contact details or screening records. It does not provide unrestricted event browsing or open-shift signup.

## Reminder drafts

Coordinators with both `volunteering.manage` and `communications.manage` can choose **Prepare email reminder**. The API also supports SMS drafts. This creates a template and a single-recipient **draft** campaign. It does not queue a delivery or contact a provider.

Review the draft in **Communications**, then explicitly queue it through the existing workflow. Channel consent and contact validation remain required. The queue and delivery worker also recheck the reminder's assignment, invitation expiry, shift version, occurrence time/location/status, and volunteer eligibility. Outdated or ineligible reminders are suppressed, even when nobody has opened the rota to settle expiry.

A changed/expired invitation is not bypassed by sending a reminder. Suppressed messages are not automatically retried; resolve the underlying staffing issue and prepare a new draft/reference. References make draft preparation retries idempotent. This milestone does not schedule recurring reminders or automatically send invitation notifications. Messages already claimed can be in flight, as with existing campaign cancellation; verify provider outcomes before creating a replacement.

## Build a service order

1. Open **Service planning** and choose **Create service plan**. Event read access is needed for the occurrence selector.
2. Select the gathering/occurrence, give the plan a title, and add ordered items with whole-minute durations, shared notes and optional HTTPS resource links.
3. Use **Move up**, **Move down**, **Add service item** and **Remove item** to arrange the order. The complete order must fit within the event's actual duration.
4. Save the draft and review it. Choose **Publish** to make it available to the occurrence's invited/assigned volunteers through **View service order** in their workspace.
5. Print the order if needed. Editing a plan makes it a draft again; publish after review. Stale versions are rejected.

A plan contains 1–50 items, each 1–180 minutes. Resources are HTTPS links without URL credentials; no file is fetched, uploaded or embedded. Notes/resources are shared with the permitted serving audience, so use the plan for material intended for those participants.

One plan belongs to each occurrence, and its occurrence cannot be changed. Event rescheduling marks it `needs_review`; cancellation marks it `cancelled`. Participant access is closed until the coordinator explicitly reviews and publishes a valid plan. Restoring an occurrence does not automatically publish its plan. Global staff readers may see historical/draft plans; volunteers can see only published plans related to their current reserved/assigned/reviewing assignments. Decline, expiry or cancellation removes that participant access.

## API and operations

- `GET /api/volunteer-rota` — coordinator overview, invitation responses and reminders.
- `POST /api/volunteer-rota/invitations` — `{shiftId, personId, expiresAt}`.
- `GET /api/volunteer-workspace` — linked person's own schedule only.
- `POST /api/volunteer-workspace/invitations/{id}/response` — `{decision: accepted|declined, expectedVersion}`.
- `POST /api/volunteer-rota/assignments/{id}/reminder` — `{channel: email|sms, reference}`; prepares a draft.
- `GET/POST /api/service-plans` — staff collection/create draft.
- `GET /api/service-plans/mine` — scoped published plans.
- `GET/PUT /api/service-plans/{id}` — authorised view/edit. Edits include occurrenceId, title, items and expectedVersion.
- `PUT /api/service-plans/{id}/publication` — `{expectedVersion, published}`.
- `GET /api/workflows/service_planning/{id}/audit` — privileged metadata audit journal.

Invitations and reminder references use existing Volunteering document storage. Plans use `serviceplanning_records`. Mutations, responses, reservation updates and audit entries share the church workflow lock/transaction. Integrations preserve cancellation/rescheduling history. Communications' tagged delivery guards enforce live domain rules before provider claim.

Collection pagination/indexed projections, scheduled automatic notifications, recurring rotas, open-shift signup, uploaded song/media libraries, item completion tracking and full worship-resource management remain extensions. No provider configuration or real messages are sent as part of implementation verification; tests use isolated data and a fake gateway.
