# Context ownership and integration

The application remains a modular monolith. Each bounded context owns its domain,
application use cases, adapters and HTTP boundary. Contexts integrate through
published `application.contract` interfaces; database tables and domain aggregates
are private implementation details.

## Children Ministry

Children Ministry is divided into cohesive internal modules. Every module retains
Clean Architecture layers and dedicated command/query packages. Tests mirror the
production packages.

| Module | Ownership |
| --- | --- |
| participation | Programmes, classes, rooms, locations, enrolment and placements |
| sessions | Recurring scheduling, teams, staffing, admission, attendance, handover and closeout |
| safeguarding | Guardian authority, consent policies/evidence, collector grants, confidential support and readiness |
| incidents | Restricted incident records, assignment, follow-up, closure and retention |
| messaging | Guardian eligibility, contact verification and ministry message approval |
| reporting | Read-only attendance reports and ministry overview composition |
| shared | Ministry rules, failures, locking, auditing and common record mapping |

These are internal modules of one bounded context. Their domain types may be used
within Children Ministry. Admission and handover continue to coordinate the
participation, safeguarding and session rules in one transaction. Child/person
locks precede session locks; UUID ordering and live eligibility checks remain
mandatory. Reporting may compose module read contracts and must never access
private support or incident payloads without their explicit audited use cases.

## Communications

Communications owns reusable email templates, approved message snapshots, delivery
state, dispatch, transport, retry rules and provider receipt history. Template
variables are explicitly declared; preview and rendering reject missing or unknown
variables and escape supplied values. Rich text is sanitized before persistence
and rendering. Template edits never change already approved delivery content.

Children Ministry owns the meaning and eligibility of guardian messages. It
supplies approved content and an opaque source reference through Communications
contracts. Its adapter revalidates recipient authority, contact verification,
consent policy and the approver's live permissions before dispatch. Communications
has no dependency on Children Ministry aggregates, stores or application services.
Contact verification remains in Children Ministry.

Existing delivery rows are moved by a forward Flyway migration. Existing Children
HTTP routes remain compatibility entry points. New template administration lives
under `/api/communications/templates` and requires Communications permissions.

## Future safeguarding extraction

Safeguarding and incidents remain Children Ministry modules until church-wide
screening, incident and protection workflows establish a shared model. A separate
bounded context must define its own language, lifecycle and contracts before any
extraction. A separate deployment is not a prerequisite for that decision.

## Volunteer & Team Management

Volunteering owns People-linked volunteer profiles, optional login links, teams,
role definitions and dated membership/approval records, individual credentials,
weekly availability and dated exceptions, shifts, commitments, invitations and
reviewed reminders. Its normalized tables use the `serving_` prefix. There is no
PHP data migration or dependency on the PHP JSON record model.

Planning and operational readiness are separate. A proposed or confirmed
commitment can exist while preparation is incomplete. Readiness lists every
missing requirement and never silently interprets unknown availability as consent.
Child Ministry staffing accepts only a confirmed assignment for the exact session,
responsibility and full duty interval, then retains its own verified-worker,
adult, account, screening, training and presence checks.

Children Ministry publishes session metadata through `ActivitySource` and
serializes linked changes through `ServingActivityLocks`. Volunteering publishes
`StaffingDirectory`; Children owns its opaque assignment links. Cross-context
access never joins or writes another context's tables. Worker/person locks precede
sorted session locks, volunteer aggregate locks and shift locks. Multi-volunteer
changes lock every person first. Team eligibility changes lock affected sessions
before the team aggregate, serializing them with admission decisions.

Communications renders the published `volunteering.shift-reminder` template and
owns delivery lifecycle/transport. Volunteering reviews and freezes the recipient
and content, records verification evidence and supplies a source eligibility
adapter. Dispatch rechecks live sender permission, People contact, active profile,
confirmed assignment, linked source and unchanged schedule. Uncertain sends retain
the existing conservative retry rules. Template revisions do not rerender approved
messages.

Current sources are standalone duties, Java Children Ministry sessions and Java
Events occurrences. `ActivitySource` publishes an explicit operational scheduling
revision and venue label. Source locks serialize serving changes with originating
schedule edits. Existing Children sources retain their compatible timestamp
revision. The Java application never reads legacy PHP occurrences.

## Events

Events owns definitions, finite recurrence series, stable dated occurrences and
its venue catalogue. Definitions carry metadata; recurrence rules create dated
occurrences. A moved date retains its ID and immutable recurrence origin. Single
occurrence overrides survive later future-series rescheduling. Future cancellation
includes overrides; restoration is an explicit single-date command.

Read-only previews validate strict local DST times, active venues and exclusive
use conflicts. Applying a preview re-locks relevant aggregates, checks optimistic
versions and recomputes a hash of the reviewed dates, policy versions and booking
counts. PostgreSQL exclusion constraints also protect exclusive venue reservations.
Existing dates retain venue name/address snapshots when a venue is renamed.

Registration policies, person registrations and append-only histories are separate
records. Registration uses published People identity, locks the person before the
occurrence, allocates queue positions atomically and promotes waitlists in FIFO
order. Cancellation closes registration and cancels active bookings in set-based
SQL. Restoration never resurrects bookings. This phase supports free registration;
paid tickets await a trusted Payments contract.

Scheduling locks the event and series, sorted venues, then sorted occurrences.
Registration locks only its person and occurrence, so unrelated weeks do not share
a workflow lock. Calendar and catalogue queries are date-bounded and paginated.
Volunteering consumes occurrence metadata and locks through its published activity
contracts, without accessing Events stores. Venue-only changes invalidate source
readiness and require confirmed duties to be reviewed and reconfirmed.


### Event day and communications

Events now owns attendance independently of registration. One attendance record
per registration retains its original arrival and an append-only correction
history. Person locks precede the occurrence lock for arrivals, walk-ins,
corrections and notice approval. The occurrence serializes capacity allocation and
waitlist promotion; there is no global desk lock. Cancellation preserves actual
attendance, and rescheduling cannot move checked-in people onto another date.

Attendance rosters and reports use read-only contracts and paginated generated
jOOQ queries. Absence is derived only after a scheduled date ends. Reports do not
copy sensitive Children Ministry information or confer supervised check-in rights.

Events supplies its own notification review, verification evidence, immutable
source fingerprint and `DeliveryEligibility` adapter. Communications owns the
published templates, sanitized rendering and delivery lifecycle. Approval freezes
content; dispatch checks current source state without rerendering. No Events code
accesses another context's persistence. General event confirmations, reminders,
cancellations and rescheduling notices remain separate from guardian messaging.

Events owns occurrence readiness composition and the independent, versioned closeout
record. Volunteering publishes `ActivityStaffing` totals without volunteer identities
or domain objects. Communications publishes frozen-delivery summaries; Events retains
its own occurrence-to-notice association and never reads Communications tables.
Identity Access publishes `AccountNames` for bounded audit-name projections. Closeout
history preserves reviewed figures while late attendance corrections retain their own
audit trail and make the current closeout visibly stale.

## Groups and Ministries

`communities` owns ministry/group definitions, immediate dated participation,
leadership appointments, frozen group meeting rosters, group attendance and ordinary
participation follow-up. It publishes `CommunityParticipation` as a bounded person
projection. Definitions share a normalized catalogue with an immutable kind; only
groups may reference a parent ministry. Serving teams and safeguarding authorization
remain separate responsibilities.

Events publishes `CommunityCalendar`, `OccurrenceMovementPolicy` and
`OccurrenceScheduleListener` for group scheduling without foreign-table access.
Scheduling locks source event/venue/occurrence state before community/meeting state;
attendance locks People identity before source occurrence, community and meeting.
Membership and appointments lock People before community. No global workflow lock is
introduced. Communications owns templates/delivery; Communities owns recipient
membership and scoped sender eligibility. Identity Access owns active-login and live
permission checks. People owns person identity and active email addresses.

## Giving

`giving` owns funds, immutable gifts and allocations, full dated reversals,
and confidential financial history/totals. It references People identity through
`PersonDirectory`; it does not access People persistence or confer People access.
Anonymous gifts have no synthetic person. Other contexts cannot infer giving access
from ordinary directory or leadership permissions. No financial participation data is
published on ordinary People profiles.

Giving records received money through manual commands or trusted Payments confirmation.
Acknowledgements are issued through an explicit Giving command; they are not a posting
side effect. Payments owns collection and completed provider refund evidence; Giving
applies those refunds through its published contract. PostgreSQL enforces
balanced allocations, immutable evidence, reference uniqueness and one full reversal.

Giving's commitments, acknowledgements and reconciliation are cohesive responsibilities
inside the same bounded context, with separate reader/repository contracts and focused
jOOQ stores. Pledge assignments reference owned immutable gift/fund allocations and do
not constitute new money. Reversals drive current pledge progress, acknowledgement VOID
status and reconciliation review flags through read projections; no background writer
or cross-context table access is needed. Statement/CSV queries compose signed ledger
entries in one bounded database snapshot. Accounting providers remain an external
integration boundary. Online giving now integrates with Payments through the
published capability described below.


## Payments

Payments owns hosted giving checkout intent, authenticated provider notifications,
verified charges, completed refund evidence, monthly subscription lifecycle and encrypted
tokens, and immutable versioned settlement reviews. It calls Giving only through
`OnlineGivingLedger`; neither context accesses the other's tables or aggregates.
Payments uses published People identity/contact contracts and Identity Access permissions.
Callback authentication is an explicit trusted provider entry point, not an administrator
session or caller-supplied payment assertion. Provider network calls occur outside
transactions; durable verification and ledger posting use separate transactions.

Giving now accepts trusted verified online posts and owns negative dated refund entries.
Refunds preserve original gifts, void acknowledgements, cancel source pledge assignments
with evidence, and make reconciliation net amounts/review snapshots reflect changes.
Ticket revenue and charitable gifts remain separate; no paid ticket integration is exposed.

Payments also owns restricted provider settings, immutable encrypted credential revisions,
non-charging connection-check evidence and configuration audit history. New payment orders
reference their own configuration revision; settings changes do not mutate payment evidence.
The encryption master key remains deployment-owned. Credential replacement does not revoke
provider-side credentials or alter previously configured webhook subscriptions automatically.

Payments uses Communications' published template and delivery contracts for reviewed donor
confirmations. Payments retains charge eligibility, recipient approval and immutable receipt
source evidence; Communications owns frozen delivery content, transport attempts and status.
Provider webhook administration and encrypted recovery remain within Payments. Neither flow
exposes another context's persistence or permits simulated evidence to enter Giving.

### Donor self-service giving

Giving owns explicitly verified account/person financial-access links and the
opt-in online fund catalogue. Links are independent of People household roles,
Volunteering and Communities account associations. Changes require identity and
Giving management, current versions and immutable evidence. Identity Access
publishes `AuthenticatedAccount` for live caller/session validation. Payments uses
Giving's `DonorIdentity`, command-only `DonorIdentityGuard` and
`OnlineFundDirectory` contracts. Donor payment projections contain only personal
progress/refund/confirmation data, excluding staff operational and bank evidence.
Existing provider verification and ledger posting remain independent of donor
session/link lifecycle.

### General member identity and native clients

Identity Access owns verified general account/person bindings and device sessions.
Published `MemberIdentity` and command-only `MemberIdentityGuard` support People
self-service. General bindings do not grant financial, household or guardian
access. Giving publishes a binding consistency policy; general and donor identity
changes serialize on the account before link/People locks. People owns profile and
contact history, while staff retain birth-date, membership and lifecycle changes.
Payments owns the one-time native-to-browser hosted-checkout handoff. The app
remains a client of these published HTTP contracts, with no direct persistence or
provider-secret access. See `../docs/member-mobile-api-review.md`.

### Member event participation

Events consumes Identity Access's `MemberIdentity`/`MemberIdentityGuard` for own
bookings and the trusted `MemberAdmissionGuard` for authorized staff validation of
an issuing account's locked member revision. Session/account status is read through
published contracts. The catalogue is explicitly published by Events, while People
availability, event registration, attendance and child collection remain separate.
Member projections contain only event details and the caller's booking status.

### Member community views

Communities consumes Identity Access's `MemberIdentity` to derive the current
member's People identity. Its own reader filters current half-open memberships
and active community definitions before returning group/ministry details. Current
leader names use People's published directory; meeting schedule batches use
Events' read-only `CommunityScheduleReader`. Parent ministry names are descriptive and do not grant
membership or access. Personal attendance remains independent of event admission,
volunteer readiness and child collection, and read requests never prepare rosters.

Member volunteering uses Volunteering's explicit account link, independently of
general member and financial links. Its member projection exposes own upcoming
duties and readiness; source labels/venues come through `ActivitySource`. Own
invitation responses and availability replacement reuse existing locked, versioned
planning/profile operations and require live `volunteering.serve`. No automatic
link, permission, team membership or credential is granted by the mobile app.

Children Ministry Sessions owns member family projections over the context's
existing guardian, participation and attendance data. Identity Access publishes
verified member identity; safeguarding authority and pickup grants stay independent
of household and People relationship facts. Own queries expose minimal preparation
and history; they exclude care, incidents, evidence and other collectors. Member
collection labels use the existing attendance aggregate/hash mechanism and do not
grant station authority. Assigned staff review arrival references and perform the
existing guarded arrival/handover operations explicitly.

### Member notifications

Communications owns the account-scoped member inbox, read state and versioned
inbox-topic preferences. Published `MemberNotifications` accepts a fixed kind and
stable source identity from trusted internal producers, never arbitrary rendered
personal content or recipient assertions from a client. Volunteering and Events
publish within their source transactions; no external delivery occurs there.
Deduplication retains suppressed source keys. Read-only inbox contracts expose
only generic fixed messages and destinations, while destination contexts retain
all identity, readiness, booking and safeguarding authorization. Inbox preferences
are independent of email consent/templates. Push delivery and reminders remain
separate future capabilities.

### Device push delivery

Communications owns device registration, independent versioned push-topic opt-ins,
encrypted tokens and the per-notice/device outbox. Existing notification producers
continue using `MemberNotifications`; only newly inserted notices enqueue eligible
push work in the same source transaction. Hidden inbox notices can independently
produce push when that channel was explicitly enabled.

Identity Access supplies trusted account/session resolution and live directory
checks through published contracts. Installations retain a private capability;
account changes cannot inherit the previous account's notice scope or bypass a
live previous sign-in. Generic payloads expose only the notification UUID.
Destination contexts continue enforcing their own member permissions.

The bounded scheduler is a trusted outbox adapter, restricted to persisted jobs,
active registrations, live accounts/sessions, current revisions and explicit topic
opt-ins. It does not acquire administrator authority. Provider I/O runs outside
transactions; claims and receipts are version-bound, and uncertain send outcomes
are not automatically repeated. Tokens and private transport configuration remain
inside Communications infrastructure. See `../docs/device-push-setup.md`.
