# Bounded contexts: implemented workflows and next extensions

The remaining contexts were implemented in dependency order: Volunteering, Children's Ministry, Giving, Attendance, Ministries, and Communications. Each has a portal screen, authenticated API, context permission, typed domain models, DBAL persistence mapper, migration, and HTTP acceptance coverage. This is the first operational release of these contexts; extensions below are deliberately separate work.

## Events

Events owns church gatherings; LifeEvents owns personal milestones. A ChurchEvent has a finite schedule of stable EventOccurrence IDs. One-off events and weekly schedules of 1–52 occurrences use local inputs in the event's IANA timezone. Persistence uses UTC and reads include the local offset. Weekly generation preserves wall-clock times across daylight-saving changes and rejects nonexistent hours. Ambiguous fall-back hours use PHP timezone resolution.

Timezone and recurrence remain fixed after creation. Details and individual occurrence times, locations and status can be edited. Cancellation preserves history; restoration preserves identity. Staff registration now includes per-occurrence capacity, ordered waitlists, cancellation history, and paginated rosters. See [the single-church feature roadmap](feature-parity-roadmap.md). Monthly recurrence, public registration forms, ticketing, and bulk series edits remain extensions.

## Volunteering

Roles define screening and qualification requirements. Profiles store approved roles, availability windows, qualifications and expiry dates. Shift requirements reference an occurrence and role, cover a time interval inside the occurrence, and specify positions. Assignments require active eligible profiles, full availability, unexpired screening/qualifications, capacity and no overlap. The rota shows vacancies and retains declined/cancelled assignments.

Event rescheduling flags shifts and assignments for review. Edit the shift to confirm its current times/status, then reconfirm each assignment; eligibility and conflicts are checked again. Cancellation cancels staffing; restoring an event never silently recommits volunteers. Managers perform these actions; volunteer self-service, acceptance invitations and reminders remain extensions.

## Children's Ministry

Programmes own age-bounded classes and capacities. Enrolment validates the child's People date of birth and class capacity. Sessions reference Events occurrences and confirmed, eligible screened Volunteering assignments for the same occurrence (partial-event shifts are accepted). Pickup authorisation is explicit, revocable and expires by date; a family relationship alone grants no collection right.

Check-in requires active enrolment, age eligibility, capacity, currently eligible staffing and the arrival window (30 minutes before the occurrence until its end). A child can have only one open check-in across sessions. Collection records the authorised adult, supervising staff member and timestamp. Cancelling a session closes arrivals and preserves open custody; the original staff can still supervise authorised collection. A rescheduled session must be edited to reconfirm staffing before arrivals reopen. Existing supervising staff cannot be removed while children remain checked in.

Physical collector identity verification remains a staff responsibility. Medical/support records, automatic guardian policies, check-in labels, self-service registration and safeguarding case management remain extensions.

## Giving

Funds, manual gifts, allocations, pledges, donation acknowledgements, linked reversals and reconciliation batches are implemented. Amounts persist as integer minor units with a supported explicit currency. The portal accepts ordinary currency amounts and converts them exactly. Allocations must match the full gift amount. Gifts may be anonymous or reference a donor and occurrence.

Idempotency keys and optional unique external references prevent duplicate gifts. Posted monetary entries cannot be edited or deleted. Corrections use full linked reversals with a reason; issued receipts become void. Totals can be filtered by period/fund, separate currencies and show gross gifts, reversals and net amounts. Reversals belong to their reversal date, so a period can have negative net giving. Reconciliation checks currency and total and prevents reusing gifts in another batch. Pledges are tracked with explicit fulfilled/cancelled states; gifts are not automatically matched to pledges.

Printable receipts are acknowledgements and make no tax-deductibility claim. Online payments, refunds through payment providers, accounting exports, automatic pledge matching, jurisdiction-specific tax documents and donor self-service remain extensions. Event cancellation never changes gifts.

## Attendance

Named attendance and anonymous visitor counts reference occurrence IDs. Duplicate entries are rejected; corrections require reasons and retain previous values with timestamps. Children's Ministry supplies a presence-only read projection. Manual attendance and child check-in for the same person/occurrence count once; child presence cannot be changed to absent/excused through Attendance. Custody details stay in Children's Ministry. Participation totals and person/occurrence filters are available. Attendance cannot create new entries against cancelled occurrences but existing records remain correctable.

Historical trends, attendance exports, automated absence follow-up and public registration remain extensions.

## Ministries and small groups

Ministries owns ministry/small-group definitions, membership, leaders, joining/leaving dates and leadership history. Active duplicate membership is rejected. Archiving preserves the roster and prevents new joining. People remains the identity owner. Multi-campus hierarchies, group meetings and member self-service remain extensions.

## Communications

Email/SMS templates, explicit audiences, per-channel consent evidence and withdrawal, draft campaigns, queueing, cancellation and delivery history are implemented. Campaigns snapshot the template. `{{name}}` is replaced for each recipient. Queueing is idempotent. Missing consent/contact suppresses a delivery, and consent is checked again immediately before the worker claims it. Cancellation suppresses pending deliveries; messages already claimed can be in flight.

The worker commits a `sending` claim before contacting the provider and sends outside the database transaction. It records transport acceptance as `delivered`; this is not proof that the recipient read the message or that an SMS handset received it. Failed or ambiguous outcomes are not automatically retried. A process crash can leave a delivery `sending`; reconcile with the provider before preparing replacement delivery. Provider callbacks, public unsubscribe links, automatic event reminders, scheduling campaigns and audience segments remain extensions.

Default providers are unconfigured. Set deployment secrets/environment variables:

- `MAILER_DSN`: Symfony Mailer transport DSN; the default `null://null` cannot send.
- `MAILER_FROM`: verified sender address.
- `SMS_API_URL` and `SMS_API_TOKEN`: HTTPS adapter endpoint and bearer credential.

The SMS adapter posts JSON `{ "to": "+27…", "message": "…", "reference": "delivery UUID" }`, with `Idempotency-Key: delivery UUID`, and expects a successful HTTP status. A provider using another API contract needs its own MessageGateway implementation or a compatible adapter. SMS numbers require an international country code.

After configuring and approving actual campaigns, run `docker compose exec php php bin/console app:communications:deliver`. It handles up to 100 pending deliveries per invocation. Schedule the command with the deployment's worker/job system. Implementation tests use a fake gateway and send no real messages.

## Ownership, persistence and integration

Contexts own their aggregate types and repository interfaces. They reference PersonId and OccurrenceId through owner application read ports, rather than updating another context's tables. Each new context has its own DBAL document table and explicit aggregate mapper. Technical document storage is shared; business invariants are not. Records carry UUIDs, creation/update timestamps and optimistic versions. Correction, membership, custody and monetary records preserve the relevant workflow history. These are operational audit records, not a complete compliance or retention policy.

Events writes version-1 Scheduled/Rescheduled/Cancelled/Restored integration envelopes with unique message IDs into a transactional outbox. Ordered consumers record per-consumer delivery IDs and commit those markers with their changes. Volunteering and Children's Ministry react to changes; Attendance reads child presence through its owner port. Unsupported envelope versions fail processing. Operational reads/mutations drain pending integration work to keep dependent workflows current. `docker compose exec php php bin/console app:outbox:process` also drains the outbox for background processing.

Mutations use Symfony Lock and DBAL transactions, and document updates check their version. Default `LOCK_DSN=flock` serialises workflows on one host. Multi-host deployments must configure a shared Symfony lock store and a lease long enough for the database operation. Outbox workers and web nodes must use the same database and lock store. The current document queries load context collections; introduce indexed projections/pagination before large datasets.

## Permissions and deployment

Apply all migrations: `docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction`.

Each API requires its matching permission: `volunteering.manage`, `children.manage`, `giving.manage`, `attendance.manage`, `ministries.manage`, `communications.manage`. The migration seeds these and grants existing named administrator roles. Assign them explicitly to other staff roles through IdentityAccess. A person ID is never an entitlement. Current permissions are manager-level; scoped volunteer/teacher/donor self-service needs separate policies before exposure.

Portal routes are `/volunteering`, `/children`, `/giving`, `/attendance`, `/ministries`, `/communications`. Request schemas and all operational endpoints are in `backend/openapi.yaml`.

Verification: `docker compose exec php php vendor/bin/phpunit tests/Workflows tests/Events`, `docker compose exec frontend npm test`, and `docker compose exec frontend npm run build`. Acceptance coverage includes staffing conflicts and screening expiry, event propagation, reconfirming sessions, safe collection after cancellation, money/idempotency/reversal rules, deduplicated attendance, roster history, consent withdrawal and fake-provider delivery.

## Robustness changes — 11 October 2026

The complete backend test suite now passes. Audit timestamps accept explicit domain timestamps for deterministic creation/transitions, default to Symfony's clock, and reject timestamps moving backwards. SQLite test connections use the current DBAL driver configuration. New/reset account passwords require 12–4096 characters; existing password hashes continue to authenticate normally.

Account, role and permission management now require `identity.manage`, seeded for existing named administrator roles. This closes the path where an authenticated account could grant itself operational permissions or reset another account's password. `/api/me` returns the active account's permission codes. Operational and identity navigation/routes use these codes; the API remains the authority. Identity request bodies use typed, validated DTOs. Known domain failures wrapped by Messenger become useful 4xx responses; unexpected failures remain server errors, and database conflicts do not expose SQL details.

Portal edits and record actions send `If-Match: "<version>"`. The backend checks that version under the workflow lock and inside the database transaction; stale requests receive 409 and make no changes. Invalid version headers receive 422. Existing API integrations can still omit the header for compatibility; they should adopt it to prevent stale edits. The lock is checked again before commit, and a lost/expired lock rolls the operation back. A shared lock store is still required for multi-host deployments; this is not a substitute for database fencing in highly distributed systems.

Every new operational record write appends an audit journal entry in the same transaction: context, kind, record ID, action, version, actor account ID, and timestamp. The journal intentionally excludes payload snapshots containing screening, donor, contact or custody data. CLI/background execution without an authenticated account has a null actor; integration processing during an HTTP request attributes the executing account, not necessarily the author of the original event. Existing records are not backfilled with invented history. This is an application journal, not tamper-proof archival storage; database privileges and retention remain deployment responsibilities.

Record Details includes the journal. `GET /api/workflows/{context}/{id}/audit` requires that context's manage permission, supports `limit` (1–100, default 25) and a `before` version cursor, and returns `{entries, nextBefore}`. Pagination uses the indexed record/version history. The main context collection endpoints still load collections; indexed business projections and collection pagination remain necessary before operating at large scale.

Outbox processing now commits each message independently, including its consumer markers. A later failure cannot roll back previously delivered messages. Failure stops processing in sequence and keeps the failed envelope pending. Logs identify the envelope ID and exception class, without dumping payloads or exception details. Investigate that reference, fix the consumer or deploy support for the envelope schema, then rerun `app:outbox:process`; later messages are not silently skipped. Dependent workflows intentionally fail closed while an integration error remains unresolved.

Unconfigured channels leave messages pending and do not claim or fail them. SMS configuration only reports ready for an HTTPS endpoint with a credential. Delivery records include an attempt time. Operators can use `PUT /api/communications/deliveries/{id}/resolve` with `{status: "delivered"|"failed", note: "provider verification evidence"}` to reconcile failed/uncertain outcomes. Evidence is required, recorded in history, and audited. In-flight attempts cannot be reconciled during their first five minutes. A worker cannot overwrite a resolution recorded after its claim. Reconciliation never resends a message; verify provider results before creating any replacement campaign.

Run all backend tests with `docker compose exec php php vendor/bin/phpunit`, frontend tests with `docker compose exec frontend npm test`, and the production build with `docker compose exec frontend npm run build`. Apply migrations before starting the updated app. The staff pilot milestone below adds backup/restore tooling and deployment monitoring. Live-provider integration testing, actual recovery and alerting setup, collection pagination, and more explicit contracts for existing generic operational arrays remain readiness work.


## Staff pilot operations milestone

The production configuration, API read/manage permissions, account bootstrap, worker health checks, operational monitoring command, encrypted backup and isolated restore tooling, and CI browser checks are implemented. Follow [the production runbook](production-runbook.md) and [staff pilot checklist](staff-pilot-checklist.md) for rollout. A live host, real provider validation, off-site backup retention, external alert delivery, and real-user acceptance remain deployment responsibilities. Larger-data pagination, MFA, self-service account recovery, and finer access scopes remain future implementation work.


## Forms and submission review

Forms now owns reusable definitions, public publication links, immutable response snapshots and final staff decisions. Server validation, signed expiring challenges, honeypot checks, Symfony rate limiting, paginated response queues and idempotent submission references are implemented. Staff explicitly select or create/link people; event approvals use Events registration and commit atomically with the decision. See [the forms guide](forms.md).


## Giving reporting milestone

Donor period statements, currency/fund totals, dated CSV ledger exports and explicit pledge assignments/progress are implemented. Assignments can be cancelled with a reason and audit history; gift reversals affect contributions on their reversal date. These are current-record projections rather than immutable historical statements. See [the reports guide](giving-reports.md).


## Group meetings and leader workspace

Ministries now owns occurrence-linked group meetings. Managers link/cancel them; leaders with a linked active account and current leadership membership receive only their groups' rosters and Attendance operations. Attendance corrections, child-presence protection, shared transaction/audit history and membership-based revocation are enforced. Global Attendance protection is enabled, and administrator role/permission catalogues support setup. See [the guide](group-workspace.md).


## Volunteer response and service-planning milestone

Pending invitations now reserve capacity, support scoped accept/decline with live eligibility checks, expire safely and withdraw after staffing changes. Reminder drafts use consent and live delivery guards. Service Planning owns reviewed/published ordered items and resource links, scoped to serving participants and invalidated by occurrence changes. See [the guide](volunteer-responses-and-service-planning.md).
