# Member portal and account security

Members use their own church account to see personal records. Access requires an active account linked to a person, the explicit **member.portal** permission, and an enabled, verified authenticator. Staff permissions alone do not grant member access.

## Set up member access

1. In **People**, check that the member has one accurate person record.
2. Open **User Accounts** and search by username. Create an individual account if needed.
3. Open **Manage → Member access**, select the correct person, confirm that you verified their identity, and select **Enable My church**. This links the person and assigns a dedicated role containing only member.portal. Existing staff roles are preserved.
4. Add **volunteering.serve** through a separate role if that member needs the volunteer invitation workspace.
5. Ask the member to sign out and sign in again, enable an authenticator and save their offline recovery codes.

An account with no member permission now sees **Account setup required** rather than an unexplained empty dashboard. Administrators with member access see a **My church** card; if their account is unlinked, it offers **Set up member access**. Accounts with a linked person and member access go directly to the member workspace, with authenticator setup first when needed.

There is no public account registration or automatic person linking by matching an email address. Staff must establish the correct identity. Duplicate linked accounts share access to the same person's records, so provision one personal account per member.

## Enable an authenticator

Open **Account security**, enter your current password and select **Start authenticator setup**. In your authenticator app, choose **Add account → Scan QR code** and scan the displayed code. The QR code is generated locally in your browser from the server’s setup details. If you cannot scan it, enter the displayed manual key instead and choose time-based codes, issuer Divine Community Space, six digits, 30 seconds and SHA1. Enter the code and select **Enable authenticator**.

The setup expires after ten minutes. Keep the setup key private. Eight recovery codes appear once after successful enrolment. Download them and store them offline, preferably separately from the device with your authenticator. Confirm that you saved them, then continue to **My church**.

Each new password sign-in requires a fresh authenticator code. A code cannot be reused; wait for the next code if you just used one. Keep the device clock accurate. The server allows one adjacent 30-second step for clock drift. Password-only sessions for accounts with MFA expire after five minutes and cannot access operational APIs.

Staff can also enable MFA through Account security. Once enabled, it applies to their operational screens as well. This milestone requires MFA for members; it does not force enrolment on all existing staff accounts.

## Use My church

- **Personal details:** view your name and birth date; update your own email and phone. Concurrent changes require a reload. Contact changes do not change communications consent or your login username. Ask staff to correct identity or membership information.
- **My households:** see the name, your membership role and member count for households you currently belong to. Other household members' profiles, contacts, addresses, birthdays and giving are not disclosed. Household membership does not grant authority over another person's records.
- **My registrations:** view your latest 100 bookings, including waitlists and cancellations. Confirm cancellation to release your place; the existing waitlist rules may promote someone else. You cannot cancel a relative's or another member's booking.
- **My giving statement:** choose an ordered date range of at most 367 days. Only gifts explicitly recorded against your person are included. Reversals and currencies follow the existing giving statement rules. Print the statement when needed; this is an acknowledgement, not a claim of tax deductibility.
- **My volunteering:** available only when your account also has volunteering.serve. The existing personal invitation, eligibility and service-order rules apply.

New event discovery/booking remains with staff and public forms until Events gains a deliberate public visibility policy. This portal does not expose the staff calendar or attendee rosters. It does not take payments, edit relatives' data or provide a searchable member directory.

## Change a password or replace recovery codes

Use **Account security** with your current password. MFA accounts must have a verified session. Changing a password signs out all existing sessions. Replacing recovery codes invalidates previous codes and other sessions; save the newly displayed codes before continuing.

Disabling an account blocks access immediately. Re-enabling it does not restore old sessions. Permission removal also takes effect on subsequent requests.

## Recover a lost password or authenticator

Select **Recover your account** on the sign-in page. Enter your username, one saved recovery code and a new password of at least 12 characters.

Successful recovery consumes the code, invalidates every other previous recovery code and every existing session, and removes the old authenticator. Sign in with the new password and enrol an authenticator again before opening the member portal. Recovery never automatically restores a disabled or locked account.

If you have no saved code, a trusted account operator must first verify your identity using the church's agreed offline procedure. A routine staff password reset alone does not remove MFA. After verification, an operator with access to the deployment host can issue one expiring code:

    php bin/console app:accounts:recovery-code --username member.username --identity-reference verified-case-123 --code-file /secure/new-recovery-code.txt

The destination must be a new file. The command creates it with owner-only permissions and does not print the code. Transfer it through an approved secure channel and delete the file after handover. It expires after 30 minutes, and issuance revokes existing sessions and older recovery codes. Keep the verification reference in the church's accountable support process. There is no HTTP administrator endpoint to bypass MFA.

Both authenticator operations and public recovery are rate limited. Invalid recovery details produce a generic error. Wait before retrying after repeated failures.

## Upgrade, secrets and pilot

Apply migrations **Version20261012030000** and **Version20261012040000**. The new member permission is not automatically assigned to existing roles. Existing JWTs are invalid after this upgrade; everyone must sign in again.

Authenticator secrets are encrypted with a key derived from APP_SECRET. Keep a secure, recoverable copy of that deployment secret with your disaster-recovery materials, separate from the database backup. Rotating APP_SECRET without a planned secret migration makes existing authenticator secrets unreadable. Production requires PHP's sodium extension.

Recovery codes are stored only as hashes. Security audit records contain account/actor identifiers, action, timestamp and an optional offline verification reference. They contain no passwords, authenticator keys or recovery codes. Successful security changes commit atomically with audit metadata. Member and account-security responses cannot be cached. Setup keys and recovery codes are held only in component memory, except for a recovery-code file deliberately downloaded by the member.

Before inviting real members, pilot with an individual member-only account: enrol a real authenticator, sign in again, check profile ownership and giving totals, reject another person's booking ID, cancel a test booking, recover once, reject code reuse, and test administrator recovery expiry. Verify that account disabling and permission removal stop access. A live member pilot and secure distribution of initial credentials remain operational work.

## Giving online and paid tickets

Open **My church → Giving and tickets** for hosted ZAR payments through configured Payfast/Yoco accounts, monthly Payfast giving and paid event booking. See [the online payments guide](online-payments.md) for confirmation, cancellation and refund rules.
