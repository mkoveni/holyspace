# Divine Community Space mobile

Member application built with React Native, Expo SDK 58, Expo Router and TypeScript.
The Java API remains the authority for identity, authorization, People and Giving.

## Run locally

```sh
cd mobile
npm ci
cp .env.example .env.local
npm start
```

Set `EXPO_PUBLIC_API_URL` to the public API origin, without `/api` or other paths.
The backend must be reachable from the phone. On an Android emulator, a local
server is usually reachable at `http://10.0.2.2:8082`; a physical phone needs the
host's reachable address or HTTPS development tunnel. Production accepts HTTPS
only. Public environment values are bundled into the app: never put credentials,
provider secrets or the backend encryption key in them.

The Java Docker setup binds the API to `127.0.0.1` by default. For a physical phone,
set `API_BIND_ADDRESS=0.0.0.0` in `backend-java/.env` to allow LAN connections
and recreate the API container from the repository root:

```sh
docker compose --env-file backend-java/.env -f backend-java/compose.yaml up -d --no-deps api
```

Use your computer's Wi-Fi IPv4 address in `EXPO_PUBLIC_API_URL`, for example
`http://192.168.0.6:8082`. With both devices on the same Wi-Fi, first open
`http://192.168.0.6:8082/actuator/health` in the phone's browser: it must report
`UP`. If not, check the address, host firewall and Wi-Fi client isolation. Native
requests do not need browser CORS configuration. Restore `API_BIND_ADDRESS` to
`127.0.0.1` and recreate the container when phone testing is finished. A Wi-Fi
address change requires updating the app's API origin and restarting its server.

For native testing, use a development build compatible with SDK 58. The included
`expo-dev-client` and EAS development profile prepare that workflow; generating a
cloud development build requires your Expo account and platform configuration.
Application identifiers in `app.json` are initial defaults to confirm before a
first store release. No platform credentials are checked in.

### Android emulator

Install Android Studio's Android SDK and a compatible JDK, configure `ANDROID_HOME`
and `JAVA_HOME`, and start an emulator through Android Studio's Device Manager.
Run these commands from the repository root:

```sh
cd mobile
EXPO_PUBLIC_API_URL=http://10.0.2.2:8082 npm run android -- --device
```

This builds and installs the development app, then starts its development server.
The first build downloads native build dependencies and takes longer. Expo generates
the ignored `android/` folder automatically; do not create it by hand. A local debug
build does not require an Expo account or store signing credentials.

For later sessions with the development app already installed:

```sh
EXPO_PUBLIC_API_URL=http://10.0.2.2:8082 npm run android:start
```

Re-run `npm run android` after changing native dependencies or native configuration.
If the web preview already uses port 8081, choose another port when prompted, or
append `--port 8083` to either command. Stop an existing development server before
restarting it with a different API origin.

Use Expo's commands above instead of `npx react-native run-android`: the React Native
CLI expects an existing Android project and reports **Android project not found**
before Expo has generated one. In an IDE, configure an npm run target for the
`android` script in `mobile/package.json` rather than a React Native CLI target.

The development web preview is available with `npm run web` at
[http://localhost:8081](http://localhost:8081). It stores credentials in memory
only. Configure that exact origin in the Java API's `CORS_ALLOWED_ORIGINS`.

## Enable member access

Create the account and People record through the existing staff workspace. Under
**Administration → Member access**, verify identity and link them with evidence.
This requires identity and People management. Under **Administration → Donor
access**, independently verify financial access and offer online funds. Both
bindings must reference the same person. An unverified account can sign in and
manage its own device sessions but cannot view a personal People or giving record.

Forgotten-password recovery is office-assisted initially. A verified administrator
can reset the account password through existing account administration, revoking
sessions. Changing your own password requires the current password and signs out
all devices. Default sessions last eight hours; expiry requires signing in again.

## Member features

- Sign-in, restored native sessions and explicit sign-out.
- Personal home and verification status.
- Own name/title/gender and versioned, historical contact updates.
- Own contribution/payment history, online giving and monthly cancellation.
- A short-lived system-browser payment handoff, without putting login tokens in URLs.
- Own active devices and revocation; password changes.
- Published events, own bookings/waitlists/cancellation and attendance QR.
- Current own groups/ministries, leaders, upcoming meetings and personal attendance.

Use a public HTTPS backend origin that matches the payment settings' backend URL
for mobile checkout. After paying, close the system browser and refresh the app.
Only verified provider events can confirm a payment. Child collection and volunteering capabilities are planned subsequent phases.

## Verify

```sh
npm run typecheck
npm run lint
npm test
EXPO_PUBLIC_API_URL=https://api.your-church.example npx expo export --platform all
```

The last command validates JavaScript/assets for Android, iOS and web; it is not a
signed native build or a substitute for physical-device testing. Web workflow
coverage uses Playwright (`npx playwright test`) against the running web preview.
Tests mock member APIs and do not charge a payment provider.

See [the member API review](../docs/member-mobile-api-review.md) for ownership,
permissions, routes, planned work and the payment-handoff rules. Expo API references:
[SDK 58 SecureStore](https://docs.expo.dev/versions/v58.0.0/sdk/securestore/),
[SDK 58 WebBrowser](https://docs.expo.dev/versions/v58.0.0/sdk/webbrowser/),
[protected routes](https://docs.expo.dev/router/advanced/protected/).

## Production builds

Builds use EAS with remote version numbers (`cli.appVersionSource: "remote"`); the
`production` profile auto-increments `versionCode`/`buildNumber`. Bump the
user-facing `version` in `app.json` for each store release. Profiles:

| Profile | Output | EAS environment |
| --- | --- | --- |
| `development` | Dev client, internal | `development` |
| `preview` | Android APK / iOS ad hoc, internal | `preview` |
| `production` | Android App Bundle / iOS store build | `production` |

The `preview` and `production` profiles point at the Symfony API,
`https://holyspace-api.rivalani.dev`, through `EXPO_PUBLIC_API_URL` in `eas.json`.
Mobile checkout requires the payment settings' backend URL to use this same origin.
Before the first release, set the remaining public values in the matching EAS
environment (they are bundled into the app, so never secrets):

```sh
npx eas-cli@latest env:create --environment production --name EXPO_PUBLIC_EAS_PROJECT_ID --value <project-uuid> --visibility plaintext
npx eas-cli@latest env:create --environment production --name GOOGLE_SERVICES_JSON --type file --value ./google-services.json --visibility secret
```

`app.config.js` fails `preview` and `production` builds when the API origin is not
HTTPS or the EAS project ID is missing, instead of shipping an app that cannot
sign in. Release builds block Expo's default `SYSTEM_ALERT_WINDOW` and legacy
storage permissions, disable Android backup (tokens live in SecureStore) and
declare iOS export compliance as exempt (HTTPS only).

```sh
npx eas-cli@latest build --profile production --platform android
npx eas-cli@latest build --profile production --platform ios
npx eas-cli@latest submit --profile production --platform android
```

Android submissions go to the Play internal track as drafts; promote them in
Play Console after device acceptance. iOS submission needs the App Store Connect
app and Apple team, chosen interactively or added under `submit.production.ios`.
Application identifiers become permanent after the first store upload.

## Toolchain and dependency security

Use Node 24 LTS; `mobile/.nvmrc` pins the runtime used by the release checks.
With nvm, run `nvm install` and `nvm use` from this directory. SDK 58.0.23 and
React Native 0.88.1 are aligned using Expo's installer. The previous high-severity
Zod finding is resolved with the supported SDK patch and Zod 4.6.7 within the
ESLint dependencies' declared ranges. No dependency override or SDK downgrade is
used. Low/moderate audit findings still require review; CI rejects high/critical
findings.

Rebuild and reinstall the development app after these native dependency changes.
A Metro reload cannot update native modules. Signed builds, provider credentials
and physical-device acceptance remain separate production release steps.

## Member events

The Events tab offers published gatherings for the next 180 days and a separate
My bookings view. Booking identity comes from verified member access, independently
of donor access. Members can reserve a place, join a waitlist, cancel their own
booking, and re-register with its current version. The server owns capacity and
FIFO promotion; a waitlist is never presented as confirmed attendance.

In the staff workspace, open an event and choose **Publish to member app**. Existing
events start unpublished; internal/community meetings are not exposed automatically.
Configure each occurrence's registration policy to open bookings. Withdrawal stops
new member registrations but keeps existing own bookings visible and cancellable.

Confirmed members can explicitly generate a five-minute attendance QR during the
admission window (two hours before the gathering until its end). The welcome team
uses **Attendance desk → Scan member QR** on that occurrence, checks the person is
present, and chooses **Record arrival**. Camera access needs HTTPS or localhost;
a QR photo or connected scanner is also supported. QR codes are generated locally,
kept only in memory, hidden on expiry/background/navigation, and never sent to an
external image service. No native module was added for QR rendering.

A code is single use, scoped to a live member session, booking version and schedule
revision. The server rejects cancellation, stale schedules, expired sessions and
revoked member identity. It has no authority for child check-in, collection,
financial access or another person's booking. An uncertain scan should be checked
against the refreshed attendance roster before trying again.

## My groups and ministries

The Groups tab lists only current memberships on active groups and ministries.
Use All memberships, Groups or Ministries to filter, and open a membership to see
its description, campus, role, current leader names and upcoming meetings. A parent
ministry name is context only; joining a group does not automatically join its parent.

The home screen shows upcoming group meetings for the next 180 days. Meeting
screens show the authoritative time zone, venue, cancellation/closure status and
only the signed-in member's attendance. Unrecorded, not prepared, not listed,
present, absent and excused are distinct. Your group leader manages attendance;
these screens do not check anyone in or grant event/children's QR authority.

The church office manages memberships and leadership in the existing Groups &
Ministries staff workspace. After verifying general member identity, no extra staff
permission or donor link is required for these own reads. Ended memberships,
archived communities and revoked member identity remove access on refresh.
No roster, leadership login binding, personal leader contact details, follow-up
notes, delivery content or other member's attendance is exposed.

The main tabs remain Home, Events, Groups, Give and My profile. Open **Account &
devices** on Home to manage security and use **Back to home** to return. No native
module was added: existing development builds can load this update by reloading.

## My volunteering

Open **My volunteering** on Home. The next-duty card and full paged schedule cover
ongoing and upcoming duties in the next 90 days, including visible cancellations.
Times use the device time zone, displayed beside each duty. View the team, role,
activity/location where supplied by its source, and current readiness issues.
An accepted duty is a commitment; it does not certify screening or readiness.

Self-service requires the live `volunteering.serve` permission and an explicit
volunteer-to-account link set by authorized church staff. General membership,
group membership and donor links do not create this authority. Missing access or
links have an explanatory state; paused/archived profiles remain readable but
cannot respond or edit availability.

Only pending, unexpired invitations for an unchanged duty can be accepted or
declined. Other assignments should be discussed with the coordinator. Availability
supports dated weekly windows (including overnight windows) and unavailable
periods. The calendar selects dates; time choices are half-hourly, with an explicit
IANA time zone. Existing windows with finer precision are preserved unchanged.
Weekly validity ends on the exclusive date shown. Unavailable exceptions override
weekly availability. Ambiguous or skipped daylight-saving times are rejected.
Saving replaces the reviewed availability using its original version and does not
cancel existing duties. On a conflict, close and refresh before editing again.
No mutation is retried automatically. No new native module or rebuild is needed.

## My children & family ministry

Open **My children & family ministry** on Home. The app retains five main tabs;
family screens open separately. Church staff first verify the member account's
People identity and separately record current, evidenced Children Ministry guardian
authority. Households, parent relationships, email matches, donor links and staff
roles never infer member guardian access. Pickup-only adults do not gain guardian
profile/history access through a collector grant.

Each authorized child has Programmes, Sessions and Attendance views. Programme
readiness is a snapshot for today in the church time zone. Upcoming sessions cover
90 days using enrolment and class placement on the session location's calendar
date, including exclusive end dates. Readiness never reserves capacity. History
starts at the beginning of current guardian authority and excludes support notes,
incidents, other collectors, evidence and staff audit details.

A **check-in reference** contains only the child/session IDs. It helps assigned
staff find the expected child; it is not identity, consent, admission or a reserved
place. Staff scan it in the session workspace, review preparation, then separately
confirm arrival. The existing check-in command rechecks current admission, safe
capacity and staffing. The mobile display opens only during the open session's
30-minute-before-start to end arrival window when preparation has no issues.

**Create collection QR** appears for an open visit and requires the member's own
current pickup grant as well as guardian access. The review explicitly confirms
that creating it invalidates every previous printed/digital pickup label. A new
five-minute label is shown once; its hash replaces the old label under the existing
attendance version. It remains a normal pickup label, not a login/session token.
General account logout/revocation prevents new issuance but does not revoke legal
pickup authority or an already-issued label. Replacement, transfer and collection
invalidate labels; expiry and live collector/guardian checks still apply at
handover. Staff scan it in the collection dialog, reload the current version,
choose the currently authorized collector and confirm identity in person. Codes
alone never prove identity or authorization.

QRs are rendered locally and held in memory. Leaving the screen, refreshing,
backgrounding or expiry hides them; an in-flight response cannot restore a code
while hidden. No automatic issuance or retry occurs. A lost/uncertain response
requires refreshing and explicitly requesting another replacement. Staff can
complete collection after session closure without requiring renewed enrolment or
consent, using the existing safeguarding handover rules.

## Member inbox and notification preferences

Open **My inbox** on Home to see new updates and the unread count. The inbox has
All updates/Unread filters, bounded paging and explicit read/unread controls.
Viewing or opening a notification never responds to an invitation or performs a
booking action. **View current details** opens the existing member workflow and
its server-side authorization; only fixed app destinations are accepted.

Notifications currently cover new volunteer assignments, invitations, duty/shift
changes, and bookings/cancellations made through member event self-service.
Messages are deliberately generic: no child names, care details, collection codes,
other members' information or external URLs. Older notices are historical prompts;
the destination screen is authoritative for current status and access.

**Notification preferences** controls future inbox updates for Serving & volunteering
and Events & bookings. Topics default on. Turning one off preserves existing
notices and source duties/bookings. A version conflict refreshes the preference;
mutations are never retried automatically. Email consent and delivery remain
separate. This phase does not request device notification permission or enable
push delivery. No new native dependency or development build is required.

## Keyboard-aware forms

`KeyboardProvider` wraps the app, and shared `Screen`/`Sheet` components use
`KeyboardAwareScrollView` from Expo SDK 58's compatible Keyboard Controller.
Focused fields scroll above the keyboard with a 24-point gap. Sheets retain a bounded scrollable body and support dragging to dismiss the
keyboard. They use the same keyboard-aware scrolling without a second keyboard
wrapper, avoiding duplicate keyboard spacing.
The bottom tabs hide during typing. Login uses the shared handling rather than a
second platform-specific keyboard wrapper.

This change adds a native dependency. Rebuild and reinstall the development app
once (`npm run android -- --device`, or a fresh EAS development build), then reload
normally for later JavaScript changes. An older installed development client
cannot load the new keyboard module through a reload alone. Generated native
projects remain managed by Expo; do not patch their manifests by hand.

For Android acceptance, focus the login password field and the last field in a
profile/password sheet, switch fields while the keyboard stays open, scroll to
submit, then dismiss/reopen the keyboard. Check both short and tall screens and
large font settings. Browser viewport checks do not reproduce a native keyboard.

## Device push notifications

Member device push is implemented through Expo Notifications. Open **My inbox →
Notification preferences** to enable a device and choose push topics. Setup is
explicit, separate from inbox/email, and defaults off. Generic previews contain
only an opaque notice ID; taps resolve an own-account notice before opening a
fixed member screen. No permission is requested at sign-in.

Sign-out attempts device revocation, while the server also checks live account,
session, lease and current opt-in before sending. Remote device revocation is not
automatically undone by a foreground refresh. Device secrets stay in native
SecureStore; Expo tokens are held transiently and encrypted in backend storage.
Web previews do not request native push permission or register tokens.

A new native development build and Expo/FCM/APNs credentials are required.
See [device push setup](../docs/device-push-setup.md) for the concrete activation
steps, deployment variables, session lifetime and delivery guarantees. Without
configuration the app keeps the inbox usable and displays a setup message.
