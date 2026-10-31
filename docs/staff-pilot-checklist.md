# Staff pilot acceptance checklist

Pilot owner: __________  Church: __________  Release: __________  Dates: __________

## Before inviting staff

- [ ] Identify a pilot owner, technical responder, and backup responder.
- [ ] Choose the supported staff roles and assign only the permissions needed.
- [ ] Verify read-only staff cannot write using either the portal or direct API requests.
- [ ] Confirm HTTPS, schema readiness, worker health, and login throttling.
- [ ] Confirm external alerts reach the technical responder; record a test alert.
- [ ] Schedule encrypted backups, verify off-site copies, and record a successful restore.
- [ ] Run a restored application smoke test, not just a database import.
- [ ] Review who may access child, donor, and personal information and agree on retention.
- [ ] Share the user guide and provide a support contact.

## Staff tasks

- [ ] Add and update a person; search by name, email, and phone through autocomplete.
- [ ] Add a relationship and confirm it appears appropriately on both profiles.
- [ ] Create a family and choose its primary contact.
- [ ] Record an original life event and find its birthday/anniversary for a month/year.
- [ ] Create a gathering; change one occurrence and review linked staffing.
- [ ] Configure registration capacity, register two people into a full occurrence, cancel the confirmed attendee, and verify waitlist promotion and preserved history.
- [ ] Verify read-only staff can view the roster but cannot configure or cancel registrations.
- [ ] Invite a linked volunteer, test scoped accept/decline and expiry, and verify pending invitations cannot provide children’s staffing.
- [ ] Publish a service order, verify participant-only access, and reschedule the event to require review again.
- [ ] Prepare a reminder draft and verify consent/expiry/changed-shift suppression before delivery.
- [ ] Assign an eligible volunteer; verify conflicts and expired screening are rejected.
- [ ] Prepare a children's session and test a linked station-only operator with no People access.
- [ ] Print paired labels on the actual printer, replace a lost pair, and reject the old code.
- [ ] Verify identity and current pickup authorisation before collection; test revoked authorisation and cancelled-session custody.
- [ ] Verify support-note permission, roster scope, and absence of notes on labels. See [the station guide](children-check-in-stations.md).
- [ ] Reject an unauthorised collector; preserve open check-ins when a session is cancelled.
- [ ] Record a donation, print an acknowledgement, reverse a correction, and reconcile totals.
- [ ] Generate a donor statement and fund CSV; verify split allocations, separate currencies and a reversal in a later reporting period.
- [ ] Apply a gift to a pledge, reject over-allocation and cancel a mistaken assignment with retained history.
- [ ] Create a linked leader account with only ministries.lead; verify own-group attendance, other-group/global Attendance denial, and leadership removal revocation.
- [ ] Link a group meeting; test dated membership, rescheduling, cancellation, corrections and child-presence protection.
- [ ] Record attendance and visitor counts without counting children twice.
- [ ] Review change history and test a stale-edit conflict.
- [ ] Use an approved test audience and verified provider to validate communication delivery/consent.
- [ ] Publish a form, submit anonymously, review the original answers, and explicitly approve/reject; verify read-only staff cannot read responses.
- [ ] Approve an event form into a full occurrence and verify waitlisting; confirm a failed approval leaves no booking.
- [ ] Unpublish and verify the public link stops accepting responses.
- [ ] Sign out and confirm private records are cleared from the browser session.

## Decide whether to expand

- [ ] Record issues with severity, owner, and a reproduction example.
- [ ] Resolve access-control, custody, monetary, recovery, and delivery defects before expansion.
- [ ] Measure performance with expected data and concurrent staff usage.
- [ ] Confirm staff can perform their tasks without developer assistance.
- [ ] Record go/no-go decision, remaining limitations, and rollback/recovery arrangements.

Go/no-go: __________  Approved by: __________  Date: __________

## Member portal pilot

- [ ] Provision a linked member-only account with member.portal and no staff context permissions.
- [ ] Enrol a real authenticator, save offline codes, sign in again, and reject a replayed code.
- [ ] Verify personal contact updates, household summaries, own booking cancellation and donor statement totals.
- [ ] Reject another person’s booking ID and attempts to select another donor in query parameters.
- [ ] Recover once, reject reused codes and old sessions, and require fresh MFA enrolment.
- [ ] Test the operator’s private-file recovery command and 30-minute expiry after offline identity verification.
- [ ] Verify disabled account and revoked permission access; preserve APP_SECRET in secure recovery materials.

See [the member portal guide](member-portal.md).

## Reporting and follow-ups

- [ ] Verify source-scoped summary sections and saved segment filters; export a formula-safe CSV.
- [ ] Check attendance deduplication, visitor separation, corrections, reversals and currency totals.
- [ ] Verify assignee-only task access, required outcomes, reassignment and stale-version refusal.
- [ ] Enable a reviewed rule, evaluate twice, and verify one task per person and rule.
- [ ] Change its segment or revoke staff access; verify the rule pauses and monitoring requires review.
- [ ] Start and monitor the followups worker; confirm rules never send communications.

See [the reporting guide](reporting-segments-and-followups.md).
