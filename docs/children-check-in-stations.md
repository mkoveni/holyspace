# Children’s check-in stations and paired labels

The station helps supervising staff receive enrolled children and record a deliberate handover to an authorised adult. Each operator uses their own account. It is not a public, unattended kiosk.

## Prepare the session

1. Create the programme and age-appropriate class in **Children’s Ministry**.
2. Enrol each child with their recorded date of birth.
3. Record explicit pickup authorisations and expiry dates. A family relationship alone does not authorise collection.
4. Schedule the event, confirm screened volunteer assignments covering its full duration, and create a Children’s session with those staff.
5. Link each operator’s account to the supervising person and grant children.station. Operators see only sessions listing their person as supervising staff. They do not need People or Children management access.

Use an individual account rather than a shared login. Account disabling, permission removal and removal from a session revoke future station requests. Staff cannot be removed while children remain in their custody. The station is online-only.

## Receive a child

Open **Children’s station**, choose the assigned session, then search its enrolled roster and select **Check in**. Arrivals open 30 minutes before the occurrence and close at its end. Active enrolment, class age, capacity, current screening and confirmed staff coverage are checked again on the server.

The page displays two labels with the same random 12-character pickup code: a child copy and a pickup copy. Print both and hand the pickup copy to the authorised adult. Labels contain the child’s name, class, occurrence start time, code and a shortened check-in reference. They contain no date of birth, contact details or support notes.

Select **Labels → Reprint labels** for another copy with the same code. For lost copies, enter a reason and select **Replace lost labels**; this generates a new code and invalidates the previous code. Give out the replacement pair together. Printing and replacement update the label record and audit metadata. Browser printing records a print request; it cannot confirm that a physical printer succeeded.

## Collect a child

Select **Collect**, choose a currently authorised adult, enter the code from the pickup copy, check the adult’s identity using the church’s agreed procedure, and confirm the handover. The server rechecks the authorisation, current code, check-in version and explicit identity confirmation. A code alone never authorises collection.

If an authorisation was revoked or expired, collection is blocked. Arrange an explicit updated authorisation through authorised staff. When a pickup label is lost, use the documented replacement process; there is no code-free bypass for a labelled check-in.

A cancelled or rescheduled session stops new arrivals until reviewed. Original supervising staff can still record safe collection of children already in their custody. Cancellation never marks a child as collected automatically.

Existing check-ins created through the staff screen can receive labels through the station. Once a label has been issued, the existing staff collection APIs also enforce the code, identity confirmation and current check-in version. Historical unlabelled staff check-ins retain the existing authorised-collection behaviour.

## Private support instructions

Grant children.support only to staff who need actionable care instructions. This is a separate permission: children.station and children.manage alone do not expose notes.

An operator with support permission can read notes only for children in their assigned session’s active enrolled roster or open custody. To edit, the operator also needs children.manage. Supervising managers can add instructions with **Support note → Save note**. Save an empty note to clear obsolete instructions. Concurrent edits require a reload rather than overwriting another update.

Keep notes brief and useful, such as allergy precautions or assistance needed. These are not a medical record or safeguarding case-management system. Notes stay out of ordinary Children overview, station rosters, labels and audit payloads. Station responses cannot be cached; station data is cleared on page/session changes.

## Upgrade and pilot

Apply migration Version20261012020000 using the normal deployment migration command. It adds children.station and children.support and grants them only to named administrator roles. It adds no broad implicit permissions. Existing JSON records hydrate with backwards-compatible defaults.

Before a live service, print a sample pair using the actual printer, paper and browser. Check sizing and readable codes; the browser layout is a generic 85 mm label, not a driver for a particular thermal printer. Practise a wrong-code refusal, revoked-authorisation refusal, lost-label replacement, cancellation with open custody and account revocation. Agree on physical identity verification and an offline custody procedure before staff depend on the station.
