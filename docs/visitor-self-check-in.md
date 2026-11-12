# First-time visitor self check-in — specification

Status: **Draft for business review.** Nothing is implemented. Open questions are in section 10.

## 1. Problem

The church wants first-time visitors to check themselves in at a Sunday service or any other church event. Today a visitor cannot do this: every `/api/**` route requires a login (`backend-symfony/config/packages/security.yaml`), and the existing walk-in (`POST /api/events/occurrences/{id}/walk-ins`) needs staff to create the person first and then register them.

## 2. Goals

- A visitor checks in from their own phone in under a minute, with no app and no account.
- The church captures enough contact detail to follow up, with the visitor's consent.
- Staff see new visitors for a service and can confirm or remove entries.
- Existing attendance reports include these visitors without a separate process.

### Non-goals (this phase)

- Children's Ministry check-in, labels or pickup codes (see `docs/children-check-in-stations.md`). Children keep the staffed station flow.
- Converting a visitor to a member, or app invitations.
- Automated follow-up messages.

## 3. Users and journeys

**Visitor.** Scans a QR code at the venue, fills in a short form, sees a welcome message.

**Welcome team member.** Shows or prints the QR code for the occurrence, watches the new-visitors list, confirms or removes entries, follows up after the service.

**Administrator.** Controls whether self check-in is enabled for an event and who may see visitor details.

## 4. Functional requirements

### 4.1 Enabling self check-in

- Each event has a setting to enable visitor self check-in (default off), alongside the existing member offer setting (`event_member_offers`).
- A user with `events.attendance.manage` can show a QR code and a short link for a chosen occurrence, and can print it.
- The link is only valid for that occurrence.

### 4.2 Check-in window

- Self check-in is accepted from 60 minutes before the occurrence starts until it ends (value configurable, default to be confirmed, see section 10).
- Outside the window the visitor sees a clear message and nothing is saved.
- Cancelled or rescheduled occurrences refuse check-in.

### 4.3 Visitor form

Fields:

| Field | Rule |
| --- | --- |
| First name | required |
| Surname | required |
| Mobile number | required unless email given |
| Email | required unless mobile given |
| How did you hear about us | optional, short list plus "other" |
| Number of adults in the party | optional |
| Consent | required checkbox, with wording supplied by the business |

The form must work at phone width, without login, and show clear validation errors.

### 4.4 What happens on submit

In one transaction:

1. Validate the token, the occurrence state and the window.
2. Look for an existing person by normalised mobile number or email.
   - Found: use that person. Do **not** change their stored details from this public form.
   - Not found: create a person with membership type `VISITOR` and the contact details.
3. Create a walk-in booking and attendance record using the existing walk-in logic.
4. Record the consent (wording version, timestamp, source `SELF_CHECK_IN`).
5. Mark the attendance as **self-reported, awaiting review**.

Submitting twice for the same person and occurrence is idempotent: the second submit shows the same welcome screen and creates nothing new.

### 4.5 Staff review

- A "New visitors" list per occurrence shows name, contact, time of check-in, how they heard of the church, and whether this is a returning person.
- Staff with `events.attendance.manage` can **confirm** or **remove** an entry. Removal voids the attendance using the existing correction flow, with a reason.
- Contact details are only visible to users holding the permission in section 6.

### 4.6 Reporting

- Self check-ins appear in the existing attendance roster and report for the occurrence.
- The report can filter to first-time visitors and to unreviewed entries.

## 5. Security and abuse protection

The endpoint is public, so it needs its own controls:

- **Signed token** per occurrence, verifiable without a database lookup of user data, revocable by regenerating it. Staff can rotate the code if it leaks.
- **Rate limiting** per IP and per token, with a lower limit on failed attempts.
- **Bot protection:** honeypot field and a CAPTCHA if abuse is observed.
- **Minimal response:** the welcome screen never echoes stored data about an existing person (prevents using the form to discover who attends).
- **No overwrite:** a public submit never edits an existing person's record.
- **Review state** as in 4.5 so junk entries do not pollute counts permanently.
- **Audit:** every self check-in is written to the event audit trail with source and token id, no raw token.
- Public route added to `access_control` as a narrow exception, in the same way as `^/api/mobile-checkouts/[^/]+$`.

## 6. Privacy (POPIA)

- Collect only the fields in 4.3.
- Show the consent text and a link to the church's privacy notice on the form. Store the wording version accepted.
- Without consent the form cannot be submitted.
- Visitor contact details require a permission distinct from general attendance reading (proposed `events.visitors.read`), granted to administrators by default.
- Retention period for visitors who never return and requests for deletion follow the church's existing people-data process. A retention value is needed from the business (section 10).

## 7. Proposed technical design

### 7.1 Backend (`backend-symfony`, Events context)

- New command `SelfCheckInVisitor` with handler under `src/Events/Application/Command/`, following the existing `RegisterEventWalkIn` handler pattern and reusing `EventAttendance::walkIn`.
- Person creation and lookup through the existing People contract (`PersonDirectory`); add a lookup by normalised phone or email if one does not exist.
- New public controller: `POST /api/public/visits/{token}` and `GET /api/public/visits/{token}` (returns event name, venue, time and whether open). `access_control` entry for `^/api/public/visits/[^/]+$`.
- Staff endpoints: `GET /api/events/occurrences/{id}/self-check-in` (QR link), `POST .../self-check-in/rotate`, `GET .../visitors`, `POST /api/events/registrations/{id}/visitor-review`.
- Migration `V39` (next free number): per-occurrence check-in token hash and enabled flag, visitor consent record, review status on attendance, permission `events.visitors.read`.
- Tests: handler unit tests (window, duplicate, existing person, no overwrite), controller tests (public access, rate limit, bad token), and architecture tests already in the repo (`deptrac`).

### 7.2 Frontend (`frontend`, `events` area)

- Staff: "Visitor check-in" panel on the occurrence page with QR code, link, print and rotate; "New visitors" list with confirm and remove.
- Public: a standalone `/visit/:token` route outside the logged-in layout, mobile first.

### 7.3 Mobile app

No change in this phase.

## 8. Acceptance criteria

1. A visitor with no account scans the QR code inside the window, completes the form, and sees a welcome message.
2. A new `VISITOR` person, a booking, a present attendance record and a consent record exist afterwards, and the attendance is flagged as awaiting review.
3. Submitting again, or submitting with the same mobile number, does not create a second person or second attendance.
4. An existing person who submits is attached to the occurrence and their stored details are unchanged.
5. Submitting outside the window, for a cancelled occurrence, with an invalid or rotated token, or without consent is refused and saves nothing.
6. Staff can list, confirm and remove entries; removal uses the correction flow and appears in attendance history.
7. A user without `events.visitors.read` cannot see visitor contact details.
8. Repeated rapid submissions from one source are rate limited.
9. The attendance report counts confirmed and unreviewed self check-ins and can filter to first-time visitors.

## 9. Rollout

1. **Phase 1:** everything above, enabled per event, piloted on one Sunday service.
2. **Phase 2:** adding children and handing over to Children's Ministry; automatic welcome and follow-up messages through Communications templates.
3. **Phase 3:** convert a visitor to a member; app invitation.

### Phase 2 as built

- **Children.** The form takes up to 8 children (first name and date of birth, under 18). They are stored with the visit as `PENDING` notes (`event_visitor_children`) and shown under the visitor to staff with `events.visitors.read`. Staff with `events.attendance.manage` mark each one **handed to Children's Ministry** or **dismissed** (`POST /api/events/visitor-children/{id}/handover`). Nothing is created in Children's Ministry from the public form: no child record, enrolment or pickup authorisation. Children's staff enrol the child in their own flow. A removed visitor's children cannot be handed over.
- **Messages.** Staff holding `events.communications.send` turn on automatic emails per occurrence (`visit-link` action `MESSAGES_ON` / `MESSAGES_OFF`). A visitor who gave an email address and consented then gets a welcome email at check-in and a follow-up at 09:00 the next day (occurrence time zone), from the published templates `events.visitor_welcome` and `events.visitor_follow_up`. The approving account is the delivery's approver, and before every send Events checks that messages are still on, the approver still holds the permission, the visitor was not removed, and the recipient and content are unchanged. Visitors with only a mobile number get nothing (no SMS transport). A missing template never blocks check-in. Existing people matched by email are emailed at the address they typed.
- Migration `V40`. Not built: a rotating on-screen code, SMS, automatic enrolment.

### Phase 3 as built

- **Make a member.** Staff holding `events.attendance.manage` and `people.manage` use **Make member** on a visitor (`POST /api/events/registrations/{id}/visitor-convert`). People turns a `VISITOR` or `REGULAR_VISITOR` into `ACTIVE_MEMBER` (audit `person.visitor.converted`); a person who already is a member is left alone, and a removed visitor cannot be converted. Repeating the request changes nothing.
- **App invitation.** With `inviteToApp: true` (also needs `events.communications.send` and an email the visitor gave) one email is queued from the published template `events.visitor_app_invite`, once per visitor (`event_visitor_messages`, purpose `APP_INVITE`). Events re-checks before sending that the sender still holds the permission, the visitor was not removed and the recipient and content are unchanged. It does not depend on the occurrence's automatic-messages switch.
- **Not built:** creating an account for the person. Accounts are still created by administrators and tied to a person through the existing member-link verification; the invitation tells the member to ask the welcome team. The template carries no app-store URL because none is configured.
- Migration `V41`.

## 10. Open questions for the business

1. Must visitors be able to add their children at check-in (Phase 1 or Phase 2)?
2. Who supplies the consent wording and privacy notice link?
3. Should the check-in window be 60 minutes before start until the end, or something else? Same for every event?
4. Which contact detail is mandatory: mobile, email, or either?
5. Who follows up with visitors, and which roles may see their contact details?
6. How long are visitor details kept if the person never returns?
7. Is a printed QR code at the door enough, or should a rotating code on a screen be used to stop sharing the link outside the venue?
