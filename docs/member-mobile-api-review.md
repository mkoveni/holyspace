# Holyspace member app: foundation and API review

## Initial delivery

The React Native/Expo application lives in `mobile/`, alongside the existing Vue
staff workspace. Both use the Java API; the mobile app has no direct database,
provider-secret or business-rule access. Its first journey is sign in, inspect and
update the member's own permitted personal details, give online, and read the
provider-verified result. The main navigation is Home, Events, Groups, Give and My profile; Security is
accessed from Account & devices on Home. Volunteering and family attendance are later phases.

## Identity and access

Identity Access owns `identity_member_links`: an explicitly verified, unique
account-to-People identity with current version and immutable verification,
revocation and restoration history. Administrators use **Administration → Member
access** and need both `identity.manage` and `people.manage`. Evidence and an
explicit confirmation are required. A revoked link preserves its person identity;
accounts cannot be reassigned to a different person's historic records.

Giving's donor link remains a separate financial grant. A member link grants no
financial, household, ministry, guardian or collector authority. Existing financial
and general member bindings must agree on the person, including revoked bindings.
Account-root locks serialize binding changes before link and People locks. There
is no automatic binding from email, names, households or existing ministry roles.

Sign-in reuses the existing opaque, revocable Java session. Its optional device
name is an untrusted display label, limited to 80 characters. The session lifetime
remains the configured lifetime (eight hours by default); the foundation does not
issue a second mobile credential or an indefinite refresh token.

Account creation and forgotten-password recovery are church-office assisted in
this phase. Existing identity administration can reset a verified account's
password and revoke its sessions. The app explains this process instead of
exposing unverified email recovery or public self-registration. Automated recovery
will require verified-contact ownership and a reviewed, expiring one-use recovery
flow before it is enabled.

## API coverage and remaining member-specific work

| Area | Foundation | Next boundary work |
| --- | --- | --- |
| Account | Existing login, own account, current-password change, logout; new own device-session list/revocation | Invitations and verified-contact automated recovery |
| People | New own profile/title catalogue; name, title and gender changes; owned, versioned contact replacement/removal | Reviewed correction requests for staff-managed birth dates and membership; own addresses |
| Giving | Existing donor-scoped history, checkout, refunds and monthly management; new secure system-browser handoff | Personal statement/acknowledgement downloads and native receipt presentation improvements |
| Events | Published member catalogue, own bookings/cancellation, staff-scanned expiring attendance QR (V27) | Physical camera acceptance; notifications and event history |
| Groups | Current own groups/ministries, leader names/roles, upcoming meetings and own attendance | Joining requests, responses and consent-aware communication workflows |
| Volunteering | Existing linked volunteer invitations and availability capabilities | Review each endpoint for member scope and expose an explicit personal overview |
| Children | Existing guardian, consent, collector and supervised attendance invariants | Explicit guardian/collector member access, short-lived credentials and supervised scanning; no authority inferred from QR codes |

Profile updates cannot change a person's ID, birth date, membership, lifecycle,
household, relationships or permissions. Contact replacement ends the old record
and creates a new one under the person's optimistic version. Contacts do not change
the account's login email and do not automatically establish messaging verification
or consent. Existing message approvals become ineligible when their source contact
changes. No financial or safeguarding data is included in profile responses.

## New endpoints

- `GET /api/member-access` and `PUT /api/member-access/{accountId}`: privileged
  verification, revocation and restoration, with current versions and evidence.
- `GET /api/member/profile`: own profile and active contacts, or `profile: null`
  while awaiting verification or after revocation. Read only and `no-store`.
- `PUT /api/member/profile`: own name, title and gender, using the person version.
- `PUT /api/member/profile/contacts`: add a primary contact or replace an owned
  contact of the same kind, preserving the old record.
- `DELETE /api/member/profile/contacts/{contactId}?expectedVersion=...`: end an
  owned active contact, with the person version.
- `GET /api/me/sessions`: bounded own active sessions, with current-device marker;
  includes no bearer token or token hash.
- `DELETE /api/me/sessions/{sessionId}`: revoke an owned session. Another account's
  ID returns 404. Revoking the current session requires signing in again.
- `POST /api/my-giving/payments/{orderId}/mobile-checkout`: authenticated,
  donor-scoped preparation of a five-minute, one-use browser capability.
- `GET /api/mobile-checkouts/{token}`: public static continuation page; no reads or
  writes of financial evidence, and no capability consumption.
- `POST /api/mobile-checkouts/{token}`: trusted capability entry point. Rechecks
  expiry, one-use state, the bound live session, account, donor link and pending
  payment before returning an allowlisted provider form or redirect. Used,
  expired and revoked capabilities show an actionable 410 page. It never posts a
  contribution or treats a browser return as proof of payment.

## Payment handoff

The app never puts its account token in a URL. Payments hashes the random handoff
capability, encrypts the hosted-checkout payload, and binds it to the initiating
account/session and original order. It uses the immutable order configuration's
backend origin. The app accepts handoffs only from its configured public API
origin and the expected one-use route. That public API origin must match the
provider settings' backend URL.

The browser continuation uses `no-store`, `no-referrer`, a restrictive content
security policy and escaped form fields. Yoco redirects only to its hosted checkout;
Payfast receives only its allowlisted HTTPS process form. Provider HTTP happens in
the existing checkout handler outside database transactions. A new handoff can be
prepared for the same order after uncertainty without creating another payment.
The app refreshes on returning to the foreground; the existing verified webhook
and Giving posting remain authoritative. Close the system browser after payment to
return to the app. No browser cookies or account-token transfer are required.

## Client security and development

Native tokens use Expo SecureStore. Web preview credentials are memory-only, so a
browser reload requires sign-in. Personal history stays in memory. A small pending
gift intent uses encrypted native storage so retries preserve its request UUID and
reviewed details. Session invalidation clears credentials, pending intent and UI
state. Late responses are rejected if the session changes, including while JSON
is being decoded. The client does not retry mutations automatically.

The app is online-first. Financial and attendance actions are not queued offline.
Protected navigation improves the interface; server authorization remains the
security boundary. Production requires an explicit HTTPS API origin. Development
CORS allows only the configured exact origins, including the local mobile web
preview on port 8081. Native requests do not rely on browser CORS.

The repository contains development/preview/production EAS profiles, without Expo,
Apple or Google credentials. Native signing, store registration and real-device
acceptance testing require the church's platform accounts and devices. No store
submission or external release is performed by this foundation.

## Implemented: member events (V27)

- `GET /api/member/events?mine=false&limit=25&offset=0` returns only explicitly
  published, active scheduled gatherings in the upcoming 180 days. Unverified
  signed-in accounts receive `verified:false` and no events. `mine=true` returns
  only that member's upcoming bookings, retaining cancelled/withdrawn gatherings.
- `GET /api/member/events/{occurrenceId}` returns a published gathering or the
  current member's own booking. Projections omit rosters, person identifiers,
  staffing, audit evidence and staff notes. Venue/details are published deliberately.
- `POST .../{occurrenceId}/registration` uses a stable `requestId`, an optional
  `expectedVersion` for re-registration, and server-derived member identity.
  Capacity, FIFO waitlist, person availability and event lifecycle use existing
  registration operations. `POST .../{occurrenceId}/cancel` accepts the own booking
  version; it cannot cancel another person's registration.
- `POST .../{occurrenceId}/attendance-pass` issues a five-minute opaque QR credential
  only for a confirmed booking during admission hours. Responses are no-store.
  `POST /api/events/occurrences/{occurrenceId}/scan-member` requires the live
  `events.attendance.manage` permission, the credential and a request UUID. It binds
  the selected occurrence, member link revision, account, live issuing session,
  booking version and schedule revision; consumption and attendance are atomic.
  Reusing a consumed code is rejected. Staff should inspect attendance after an
  uncertain response. QR data is never placed in a URL or logged by application code.
- Staff `GET/PUT /api/events/{eventId}/member-offer` requires `events.manage` at
  HTTP and application boundaries. Publication has independent optimistic versions
  and an audit trail. It does not grant roster or registration-management permission.

Commands lock the event root, then member link, People and occurrence before QR
consumption. QR scanning uses Identity Access's published `MemberAdmissionGuard`
trusted capability after staff authorization; it never reaches into foreign
persistence. Issuing sessions are referenced by identifier without a restrictive
foreign key, so logout/session revocation remains possible. V27 migrates schema
only; it does not publish events or migrate PHP data.

Mobile Events, own details/booking actions, expiring local QR display and upcoming
home bookings are implemented. Staff publication and QR camera/photo/scanner input
are integrated into the existing event workspace. Physical camera acceptance and
real provider giving acceptance still require device testing; mocked browser flows
and native bundle exports do not establish those outcomes.


## Implemented: My groups and ministries

This phase is read-only and uses the existing schema (no migration or PHP data
import). Identity comes from `MemberIdentity.currentPerson()`, including live
session/account validation, verified active member linkage and People availability.
Every query has its own package/handler and performs application authorization.
HTTP endpoints require authentication and return no-store responses.

- `GET /api/member/communities?kind=GROUP|MINISTRY&limit=25&offset=0`: current own
  memberships on active communities. Unverified accounts receive `verified:false`.
  The current membership role is descriptive, not a staff permission grant.
- `GET /api/member/communities/{id}?leaderLimit=25&leaderOffset=0`: own community
  details, optional active parent ministry name, and bounded current leader names
  and roles. Both the leader appointment and its membership must cover now. Leader
  availability comes through People's published directory. Parent details require
  their own separate current membership; no inheritance or automatic joining.
- `GET /api/member/group-meetings?communityId={optional}&limit=25&offset=0`:
  upcoming 180-day meetings in current own communities, ordered by scheduled time
  and stable identifier. Explicit community scope must be an own membership.
- `GET /api/member/group-meetings/{id}`: own meeting schedule/status and personal
  attendance. A meeting predating membership is hidden unless the member already
  has their own attendance row, and current community membership is still required.

All limits are 1–100 and offsets are bounded; pages expose `hasMore`. Own SQL joins
use generated jOOQ tables and half-open membership/leadership periods. Only the
caller's roster row is joined. Missing unfrozen attendance is `NOT_PREPARED`;
missing frozen attendance is `NOT_LISTED`; neither implies absence. Existing
UNRECORDED/PRESENT/ABSENT/EXCUSED statuses are retained exactly. GET never freezes
rosters or creates records. Source schedule cancellation overrides a stale local
OPEN state without erasing recorded attendance.

Communities reads meeting dates through Events' published `CommunityScheduleReader`
read-only batch capability; it never queries Events persistence. The production adapter
batches generated jOOQ occurrence reads. Leader names also use a published batch
capability. Payloads exclude roster totals, other members, personal contacts,
account bindings, care/follow-up notes, message contents and history evidence.
Staff/admin endpoints and permissions are unchanged. These views grant no event
admission, volunteer eligibility, financial, guardian or collector authority.

The mobile app adds Groups with filters and pagination, own details/current
leaders, paged meeting links, meeting details/personal attendance and a home-screen
upcoming-meetings section. Five primary tabs are retained by moving Security to a
protected stack screen accessible from the existing Home action. Native modules
and app-store credentials are unchanged.

## Implemented: My volunteering

Volunteering remains the owner of assignments, invitations, eligibility and
availability. Uses the existing V14 schema; no migration or PHP import.

- `GET /api/member/volunteering?limit=25&offset=0` authenticates the live caller,
  evaluates live `volunteering.serve`, and resolves the existing explicit unique
  volunteer account link. Missing permission returns `permitted:false`; an
  unlinked caller with permission receives `linked:false`. No account/person IDs,
  credential evidence, audit reasons, other volunteers or roster totals are exposed.
  Read-only generated jOOQ queries paginate the caller's ongoing/upcoming 90-day
  assignments, including cancellations. `hasMore` uses a bounded extra row.
- `POST /api/member/volunteering/invitations/{id}/respond` accepts invitation
  `expectedVersion` and `ACCEPTED`/`DECLINED`. Authenticated identity is resolved
  internally, ownership is checked before acquiring any foreign volunteer locks,
  and live access/account linkage/active status are rechecked after the own-profile
  lock. Existing roster response invariants validate pending state, expiry,
  assignment version, schedule revision and source activity. Acceptance retains
  readiness as a separate assessment; declining releases the commitment.
- `PUT /api/member/volunteering/availability` takes the own `volunteerId`, `expectedVersion`, `weekly`
  and `exceptions`. It replaces the current availability revision under existing
  People/activity/volunteer lock ordering, rechecks live serving access and explicit
  account ownership, and preserves optimistic versions and audit records. Each
  list is bounded to 100 entries. Missing links cannot be silently created.

Queries depend on read capabilities; commands have individual packages/handlers
and mirrored tests. HTTP adds permission checks for writes and all responses are
no-store. GET computes effective invitation state without expiring invitations or
releasing holds. Source metadata is read through published ActivitySource
contracts. No household/general member/donor link implies volunteering authority.
Existing staff APIs and their explicit account-link permissions remain unchanged.

Mobile Home has an isolated next-duty preview and My volunteering opens as a
protected stack route, retaining five primary tabs. The screen includes paged
schedule, readiness explanations, reviewed invitation responses, calendar-based
availability and unavailable periods. Draft versions are frozen when editing
starts; foreground refresh cannot upgrade an old draft to a newer version.
Device-zone timestamps are labelled. No native dependency was introduced.

Availability drafts also bind the explicitly linked volunteer profile ID, so a
relink cannot apply an old draft to another profile with a coincidentally equal
version. The supplied ID is checked against the authenticated account before any
write; it is never accepted as authority for another volunteer.

## Implemented: My children & family ministry

Own views live in Children Ministry Sessions and depend on read-only capabilities.
A dedicated generated-jOOQ read projection joins the context's canonical guardian,
participation, session and attendance tables; it does not access foreign context
persistence. People names/availability and verified account identity use published
contracts. There is no PHP import or automatic guardian/account/collector grant.

- `GET /api/member/children?limit&offset`: trusted live member identity plus current,
  unrevoked, half-open guardian authority. An unverified account gets
  `verified:false`. Returns only available children under 18 with known birth dates.
- `GET /api/member/children/{childId}`: minimal own authorized child identity.
- `GET .../{childId}/programmes?limit&offset`: current/future enrolments, current
  class name and descriptive readiness steps today, without consent evidence.
- `GET .../{childId}/sessions?limit&offset`: ongoing/upcoming 90-day sessions in dated
  placements and enrolments at the location's calendar date, with child preparation
  issues. No other children, staffing identities, occupancy counts or care notes.
- `GET .../{childId}/attendance?limit&offset`: own child visit dates, class/room,
  collected/in-care state and whether the caller currently has their own pickup
  rights. History before the earliest currently active guardian authority is hidden.
- `POST .../{childId}/attendance/{attendanceId}/pickup-label`: expected attendance
  version and explicit `replacePrevious:true`. Rechecks trusted member identity,
  available adult guardian, current guardian authority and own current pickup grant
  after locks. Uses the existing hashed label mechanism, not a handover bypass.
  Expiry is at most five minutes and no later than the applicable grants' expiry.
  Codes are returned once, no-store, and sensitive textual representations redact
  them. New issuance replaces every prior label. Version conflicts require refresh.

Member commands lock the member binding, sorted child/guardian People records,
child guard, session and attendance in that order. Existing guardian/consent/grant
changes serialize on the same child guard. A transferred visit is rejected until
refreshed. Issuance is independent of session arrival status and current enrolment,
so an attending child can still be collected after a session ends. Existing staff
handover verifies collector adulthood, current grant/guardian authority, in-person
identity, label expiry/hash, optimistic version and throttling, then closes the
visit atomically. No guardian gets station/supervisor permissions.

`GET /api/children/sessions/{sessionId}/arrival-reference/{childId}` is a separate
assigned-staff read requiring live `children.station`. It validates current dated
class placement/enrolment before exposing the child's name/preparation. Reference
QRs are identifiers only and grant no authority. Staff explicitly use the unchanged
arrival command after review; GET and scanning do not record arrival. Collection
scanning validates QR purpose/attendance ID before filling the normal pickup-code
input and reloads the latest attendance version. Staff must still select a current
collector and verify identity. Camera/photo/connected-scanner input stays in memory.

V28 adds a guardian lookup index and a generated read view for the session's local
calendar date. The migration's PostgreSQL time-zone function lives in the database
view; application SQL uses generated table/column/view types exclusively. Page sizes
are bounded to 1–100 and all own responses are no-store. General member access,
guardian authority and collector permission remain independently revocable.

## Member inbox (V29)

Communications owns the persistent, account-scoped inbox and inbox-topic
preferences. It publishes `MemberNotifications` as an internal transactional
capability; Volunteering and Events call it only within authorized source commands.
There is no public endpoint accepting a recipient account or arbitrary message.
Source transactions persist their notice atomically, without email or external I/O.
Unique account/kind/source keys make retries safe; suppressed notices retain a
hidden deduplication record so enabling a topic cannot resurrect old retries.

- `GET /api/member/notifications?unreadOnly=false&limit=20&offset=0`: own generic
  notices, unread count and hasMore, newest first. Limit 1–100, offset 0–10000.
- `PUT /api/member/notifications/{id}/read`: `{read:true|false}` is an idempotent
  desired state, scoped to the current account; foreign/missing IDs return 404.
- `GET /api/member/notification-preferences`: enabled/version per supported inbox
  topic (`VOLUNTEERING`, `EVENTS`), defaulting to true/version zero without writes.
- `PUT /api/member/notification-preferences/{topic}`:
  `{enabled:boolean,expectedVersion:number}` changes only that account/topic,
  increments its version and rejects stale updates with 409.

All handlers require the live authenticated account/session and responses are
no-store. A general member/People/donor/guardian binding is not needed to read an
account's generic notices. No personal source details or opaque source keys are
returned. Revoking a ministry permission does not expose its data through old
notices: fixed member destinations recheck live source authorization. GET never
marks read, creates preferences, expires invitations or sends messages.

The first producers are volunteer assignment/invitation/status/shift changes and
member event registration/cancellation. Event staff changes, scheduled reminders,
community notices and guardian notifications are future integrations; no old
messages or PHP data are imported. Inbox topic choices do not change email consent,
reviewed deliveries or source obligations. Push permission, device-token lifecycle,
provider delivery and retries are a separate next phase, not represented as active
settings in this release.

## Device push (V30)

- `GET /api/member/push`: own setup availability, public project ID, push-topic
  preferences and up to 100 own device registrations. No tokens, installation
  secrets, hashes or account-session IDs. Read-only; topics default off.
- `PUT /api/member/push/devices/{installationId}`: private installation secret,
  Expo token, Android/iOS platform and matching project UUID. Resolves the trusted
  current account/session, encrypts tokens, serializes installation updates and
  binds queued work to registration revisions. Exact renewals preserve revisions.
- `DELETE /api/member/push/devices/{installationId}`: own-device revocation,
  including another device's registration; foreign IDs return 404.
- `PUT /api/member/push/preferences/{topic}`: own independent channel preference
  and expectedVersion. Stale changes return 409 and old queued revisions cannot
  be revived by turning a topic back on.
- `GET /api/member/notifications/{id}`: authenticated own notice for push tap
  resolution, including a push-only notice hidden by the inbox preference. No
  automatic read acknowledgement or workflow mutation. Foreign IDs return 404.

Source insertion deduplicates the notice and queues at most one row per eligible
registered device, without provider I/O. Delivery rechecks the live account,
session, device lease, project and revisions; payloads contain a generic message
and notice UUID only. Tickets and provider receipts are separate from device
receipt or human reading. See [setup and operational policy](device-push-setup.md).
