# Feature roadmap for one church

This is an independently designed church management system for a single church. It does not need tenant provisioning, subscriptions, church switching, or data partitioning by church. Staff accounts and ministry-specific permissions still matter within that church.

The comparison uses One Church Software's published [features](https://onechurchsoftware.com/features/), [plan comparison](https://onechurchsoftware.com/compare/), and [group tools](https://onechurchsoftware.com/groups/), reviewed on 11 October 2026. Similar functionality is the goal; this document does not claim feature parity or compatibility with that product.

## What exists and what remains

| Area | Current implementation | Missing extensions |
| --- | --- | --- |
| People and households | Profiles, relationships, household membership, backend autocomplete, personal milestones | Duplicate review/merge, CSV import/export, custom fields |
| Events | One-off/weekly schedules, occurrences, cancellation and rescheduling | Monthly recurrence, calendar view, public event pages, registration forms, public paid ticket checkout |
| Event registration | Staff registration, per-occurrence capacity, waitlists, automatic promotion, cancellation history, paginated roster, member hosted paid tickets with expiring reservations | Guest/public registration, confirmation messages, registration deadlines, group bookings |
| Groups and ministries | Membership, leader history, linked meetings, scoped leader rosters/attendance and corrections | Public directory, join requests, group messaging, bulk attendance and trends |
| Volunteering | Screening, availability, shifts, scoped invitations/acceptance/decline, reserved capacity, guarded reminder drafts | Scheduled notifications, recurring rota, open-shift signup |
| Children | Enrolment, screened staffing, capacity, scoped staff station, paired labels/replacement, private support notes, verified authorised collection | Printer-specific integration, unattended kiosks, safeguarding case management |
| Giving | Manual gifts/allocations, pledges, statements/CSV, explicit pledge progress, acknowledgements, reversals and reconciliation; hosted ZAR payments through Payfast/Yoco and Payfast monthly giving | Provider settlement matching, automated refund initiation and additional recurring intervals |
| Attendance | Named participation, visitors, corrections, child presence projection, operational trends/CSV and recorded-evidence follow-up segments | Bulk attendance, group-specific trends, missed-service inference |
| Communications | Consent, templates, campaigns, delivery worker and reconciliation | Direct segment-based campaigns, scheduling, unsubscribe flow, provider callbacks, reminders |
| Forms | Reusable definitions, public publishing, validated/idempotent submissions, spam controls, staff review and event approval | Conditional fields, attachments, embeds, group join requests, notifications |
| Member portal | Explicit linked identity, mandatory MFA, own contact updates, household membership summaries, own bookings/cancellations and giving statements; optional existing volunteer workspace | Public event discovery/booking, household delegation, communication preferences and verified email delivery |
| Reporting and automation | Saved People segments, permission-filtered operational reports, attendance chart/CSV, accountable scoped tasks, opt-in daily automation and review monitoring | Arbitrary report builders, recurring outreach cycles, scheduled communications and additional triggers |
| Worship/service planning | Draft/published service orders, item durations/ordering, HTTPS resource links, scoped serving access | Uploaded song/media libraries, recurring templates, completion tracking |
| Facilities and resources | Not implemented | Rooms/equipment, bookings and conflicts |
| Security and operations | Staff permissions, login throttling, authenticator MFA, hashed offline recovery codes, expiring operator recovery, token revocation, security audit, production containers, monitoring/backup tooling, CI | Verified email recovery/notifications, enforced MFA rollout for all staff, finer staff scopes, configured off-site backups/alerts and a live pilot |

## Implementation sequence

1. **Event registration** — delivered in this milestone. Stabilise the staff booking process before public access.
2. **Forms and submission review** — delivered: reusable sign-up/contact forms, public opaque links, publication controls, validation, basic spam protection, and explicit staff review with event registration integration. See [the forms guide](forms.md).
3. **Giving statements and reports** — delivered: printable donor period statements, fund CSV exports, explicit pledge assignments/corrections and progress with separate currency totals. See [the Giving reports guide](giving-reports.md).
4. **Group meetings and leader tools** — delivered: meetings linked to Events, scoped rosters/attendance, corrections and revocable leader access. See [the group workspace guide](group-workspace.md).
5. **Volunteer responses and service planning** — delivered: scoped invitation responses, expiry/reserved capacity, live-validated reminder drafts, service orders and resource links. Scheduled automatic notifications and media libraries remain extensions. See [the guide](volunteer-responses-and-service-planning.md).
6. **Children's check-in stations and labels** — delivered: staff-linked session access, printable paired labels with audited reprints/replacement, separately permissioned support notes, and code/identity/current-authorisation checks. Actual printer calibration and the live custody pilot remain operational checks. See [the station guide](children-check-in-stations.md).
7. **Member portal** — delivered: explicit linked identity and permission, mandatory MFA, one-time recovery, contact updates, household membership summaries, own registration cancellation and giving statements. Public event booking and delegated household access remain extensions. See [the member portal guide](member-portal.md).
8. **Reporting, segments and follow-up automation** — delivered: saved People filters, operational summaries, attendance trends/CSV, scoped tasks with outcomes, and idempotent opt-in daily task rules. Communication remains a separate explicit, consent-checked action. See [the reporting and follow-up guide](reporting-segments-and-followups.md).
9. **Online/recurring giving and paid registration** — delivered for South Africa/ZAR: Payfast and Yoco hosted one-off checkout, Payfast monthly giving/cancellation, verified callbacks, durable duplicate protection, separate ticket charges, 15-minute capacity reservations, refund recording and staff reconciliation. Provider credentials, callback registration and a church-owned sandbox/live settlement pilot remain operator setup. See [the online payments guide](online-payments.md).
10. **Facilities, media and other optional modules** — add only when the church needs them.

Every milestone includes backend rules, staff-facing screens, permissions, migration/upgrade instructions, and caller-level tests. External providers and live infrastructure require configuration and real-world validation separately from implementation.

## Registration operating rules

Registration is configured separately for each occurrence and starts closed. Capacity is unlimited or a whole number from 1 to 1000. Staff need `events.manage` to configure, register or cancel; `people.read` is also needed for the person selector. `events.read` can view the roster, including attendee names and registration history; it does not expose contact details through this API.

A person has one registration record per occurrence. Duplicate active registration is rejected. Rejoining after cancellation retains the record/history and goes to the end of the queue. Confirmed cancellations and capacity increases promote waiting people in queue order. Closing new registrations or disabling new waitlist entries preserves the existing queue and allows its promotion when places become available.

Capacity cannot be lowered below the confirmed count. Settings and cancellation require `expectedVersion`; conflicts return 409 without changes. Lists use `limit` (1–100, default 50) and `after`, returning `nextAfter`. These cursors describe the live queue rather than an immutable snapshot: refresh the first page after changes.

Cancelled or ended occurrences reject new registrations and stop promotion. Existing registrations stay in history. Restoring an occurrence restores its configured registration policy; staff should review the roster and policy before restoring. Rescheduling preserves reservations. Notifications and attendee reconfirmation are future work; staff must inform attendees themselves for now.

Registration does not mark attendance, start custody, send messages, or post a gift. Public/guest booking remains a future extension. Member hosted ticket payments are delivered in milestone 9; see the online payments guide.
