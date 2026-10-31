# Forms and submission review

Forms is a single-church bounded context. It owns form definitions, publication links, response snapshots and staff review decisions. People remains the identity owner; Events remains the booking owner. Anonymous responses never create or merge people, record attendance, start child custody, post gifts or grant communication consent.

## Staff use

1. Open **Forms** and choose **Create form**.
2. Give the form a title and introduction. Add 1–20 fields: text, long text, email, choice or checkbox. Field keys are distinct lowercase names such as `email_address`; keep labels easy to understand. Choice fields support 1–20 distinct options. Required checkboxes must be checked.
3. Optionally select an event and scheduled occurrence. Open that occurrence's registration settings separately before approving bookings.
4. Save the draft, then choose **Publish**. Use **Open public form** to inspect the result. Under **Review responses**, copy the shareable link for visitors. Visitors can respond without a staff account.
5. Choose **Review responses**, then **Review submission**. Read the original answers, make an approve/reject decision, and record a note.
6. Search existing people before linking a response. If necessary, use **Create person after identity review**, enter their details deliberately, then search and select the new record. Creating the person and reviewing the response are separate explicit actions.
7. An approved event request must link a person. It creates a confirmed or waitlisted registration through Events, using its capacity and eligibility rules. Approval and booking commit together. Duplicate/closed/full-without-waitlist requests remain pending after a failed approval; review the conflict and reject or resolve it deliberately.
8. Unpublish a form to stop new responses. Unpublish before changing its wording or fields. Existing responses retain their submitted schema and target occurrence. Republish when ready; the same public URL works again.

Responses can be approved without a person link for general contact forms. Decisions are final in this milestone; they cannot be overwritten. Person links and event registration references appear in the reviewed response. Registrations can be managed separately in Events.

## Permissions

`forms.read` views definitions. `forms.manage` includes read and permits creating, editing, publishing and viewing/reviewing responses. Submission answers are not available to read-only staff. Linking a person requires `people.read`; creating them requires `people.manage`. Event approval additionally requires `events.manage`. Selecting event targets in the editor uses `events.read`.

Migration `Version20261011050000` creates storage and permissions and assigns them to existing named administrator roles. Assign permissions explicitly to other roles; sign out and back in to refresh menus. Fresh administrator bootstrap grants the available permissions.

## Public submission and abuse controls

Only published forms are available through a 64-character random link. The public API returns title, introduction, fields, current version and a signed submission challenge. It exposes no submission list, internal person records or event roster.

Challenges expire after two hours, require at least two seconds between loading and submitting, and are bound to the form link and revision. A hidden website field catches simple bots. Symfony's rate limiter allows ten submission attempts per client IP per fifteen minutes, returning 429 and `Retry-After` when exhausted. Invalid attempts also consume the limit. Adjust `config/packages/forms.yaml` if the church's shared Wi-Fi produces legitimate bursts.

These are basic spam controls, not a CAPTCHA or a guarantee against abuse. Anonymous responses always require review. Reverse-proxy forwarding and an accurate client IP must be configured as described in the production runbook. The single PHP deployment uses its local cache for rate-limiter state; container replacement resets it, and multiple PHP instances require shared limiter storage.

The public page retains a submission UUID while retrying a failed request. The backend stores only one response per form/submission reference. Reusing a reference with different answers is a conflict. Reloading starts a new reference; a deliberately repeated submission can still reach the review queue. Public responses contain a generic acknowledgement, not stored answers or record IDs. No confirmation email/SMS is sent.

## API contracts and persistence

Staff endpoints require authentication:

- `GET/POST /api/forms` — paginated definitions/create draft.
- `GET/PUT /api/forms/{id}` — definition/edit unpublished form.
- `PUT /api/forms/{id}/publication` — `{published, expectedVersion}`.
- `GET /api/forms/{id}/submissions` — privileged paginated review queue.
- `PUT /api/forms/submissions/{id}/review` — `{status, note, expectedVersion, personId?}`.
- `GET /api/workflows/forms/{id}/audit` — privileged metadata journal for a definition or response.

Anonymous endpoints:

- `GET /api/public/forms/{token}` — published form and challenge; no-store.
- `POST /api/public/forms/{token}` — `{answers, formVersion, submissionKey, challenge, website?}`; returns 202 for receipt awaiting review.

Edit, publication and review require the current `expectedVersion`; stale writes return 409 without changes. Lists accept `limit` (1–100, default 50) and an `after` ID cursor, returning `{items, nextAfter}`. Answer validation is server-side, rejects unknown fields and wrong types, and limits ordinary text to 1,000 characters and long text to 4,000.

Definition and response writes append metadata audit entries transactionally. Response snapshots preserve title, introduction, fields and event target. Indexed token, form/submission-reference and cursor lookups avoid loading all responses. Event approval uses the shared workflow transaction and church lock, including nested Events operations, so failed reviews cannot leave orphan bookings.

Advanced conditional fields, attachments, website embeds, group join-request integration, CAPTCHA, confirmation notifications and automated retention/redaction are future extensions. The current production frame policy deliberately prevents embeds; share the public link instead.

## Public presentation

Published event forms display the linked event title, description, occurrence date
and time in its configured timezone, location and cancellation notice when needed.
The hero uses the event banner uploaded in Events, falling back to the form banner
when no event image exists. General forms retain a simpler centered layout.

Event details are read through a dedicated Events projection and Forms query. The
public response exposes only display fields; person records, internal IDs and
registration lists stay private. The event image is served through
`GET /api/public/forms/{token}/event-banner`, which requires a currently published
linked form on every request. Unpublishing revokes both public form and image access.
Images are not cached. Missing images collapse cleanly without a broken placeholder.

Required fields and email validation are visible before sending. Failed requests
retain entered answers and the submission key so retries do not create duplicate
responses. The success screen explicitly describes staff review and does not
promise a reservation or an automatic confirmation message.
