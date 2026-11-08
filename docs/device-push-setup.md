# Enable device push notifications

The implementation is installed, but live push remains disabled until Divine Community Space
has an Expo/EAS project and platform credentials. Inbox notifications keep working.
Nothing in this setup requires importing PHP data.

## 1. Link the mobile app to Expo/EAS

From `mobile`, sign in to the intended church Expo account and create/link the
Divine Community Space project:

```sh
npx eas-cli@latest login
npx eas-cli@latest init
```

Record its public project UUID. Put it in ignored `mobile/.env.local`:

```dotenv
EXPO_PUBLIC_EAS_PROJECT_ID=your-project-uuid
```

`mobile/app.config.js` maps this value to `extra.eas.projectId`. The backend uses
the same UUID. The project ID is public build configuration; push credentials and
server encryption keys are private deployment settings.

See [Expo push setup](https://docs.expo.dev/push-notifications/push-notifications-setup/).

## 2. Configure Android Firebase credentials

In Firebase, register an Android application with package `app.divinecommunityspace.member`.
Download its client `google-services.json` into the ignored `mobile` directory.
For local builds add this to `mobile/.env.local`:

```dotenv
GOOGLE_SERVICES_JSON=./google-services.json
```

For EAS builds, supply this client configuration as an EAS file environment
variable named `GOOGLE_SERVICES_JSON`, so the dynamic app config receives its path.

Configure the corresponding FCM v1 service-account credential in EAS:

```sh
npx eas-cli@latest credentials --platform android
```

Select the Google service-account key for **Push Notifications (FCM V1)**.
The service-account key and the app's client configuration must belong to the
same Firebase project. The service-account key stays in EAS; do not put it in the
mobile bundle, repository or Java API requests.

Follow [Expo's FCM v1 credential guide](https://docs.expo.dev/push-notifications/fcm-credentials/).
For iOS, configure the matching `app.divinecommunityspace.member` app identifier, APNs key
and provisioning through EAS credentials before building the iOS app.

## 3. Configure the Java deployment

Add these values to the private backend deployment environment (`backend-java/.env`
for the local Compose deployment):

```dotenv
PUSH_ENABLED=true
PUSH_PROJECT_ID=your-project-uuid
PUSH_TOKEN_KEY=a-base64-encoded-32-byte-random-key
# Set this when Expo enhanced push security is enabled:
PUSH_ACCESS_TOKEN=
```

Generate the push encryption key once with `openssl rand -base64 32`, then store it
in the deployment secret store. Preserve it across deployments: changing it without
re-registering devices prevents old encrypted tokens from being read. Push tokens
are encrypted with authenticated AES-GCM and never returned by device list APIs.

The provider URL is pinned to Expo by default. `PUSH_EXPO_URL` exists for isolated
transport tests; do not point production at an untrusted proxy. Delivery polls every
15 seconds by default (`PUSH_POLL_MS`). Restart the API after changing configuration.
An absent project/key or disabled provider makes the member settings report that
push setup is incomplete; it does not affect the inbox.

## 4. Rebuild and opt in

This phase adds `expo-notifications` and its native config plugin. Create and
install a new development build; a JavaScript reload alone cannot install it.
For local Android builds, regenerate the Expo-managed project and install it:

```sh
npx expo prebuild --platform android --no-install
npm run android -- --device
```

Use the existing reachable API origin for a real phone. EAS development builds can
instead use `npx eas-cli@latest build --profile development --platform android`.

After signing in, open **Home → My inbox → Notification preferences**. Select
**Enable notifications on this device**, allow the OS permission, then enable the
push topics you want. Topics default off and are independent from inbox/email
choices. Permission is requested only after that explicit action. Web previews
can manage preferences and registrations, but do not register a native push token.

## 5. Validate a new update

Create a new volunteer invitation/duty change, or make a member event booking.
Existing notices are not backfilled. A generic push should appear while the app
is backgrounded. Tapping it resolves the notification through the authenticated
own-account endpoint, then opens the current volunteering/events screen. It does
not accept an invitation, record attendance or mark the inbox item read.

Foreground delivery refreshes the inbox count instead of showing an extra system
banner. Test normal background taps, a cold start, denied permission, token
rotation, sign-out and another account signing in on the same installation.

Expo ticket acceptance means the request was queued. Receipts are checked after
15 minutes and can establish provider handoff, not that a person saw the alert.
See [Expo delivery and receipts](https://docs.expo.dev/push-notifications/sending-notifications/).

## Operational behavior

- Each notice/device pair has one durable outbox row. Source rollback removes its
  notice and jobs together; duplicate source requests do not create new jobs.
- Registrations bind the installation secret, account, current sign-in, token and
  project. Their lease ends no later than that sign-in's expiry (currently an
  eight-hour session by default). Push does not extend authentication. A current
  opt-in is renewed on foreground/token refresh without asking permission again.
  After sign-out or session expiry, sign in and explicitly enable the device again;
  an OS permission already granted normally does not require another permission prompt.
- Account/session/device revocation, token revision and preference revision are
  rechecked before dispatch. Another account cannot read the old notice after a
  tap. Revoking a device in settings prevents automatic renewal until explicitly
  enabled again. Remote session revocation blocks sends even before local cleanup.
- Signing out attempts device unregistration before session logout and clears local
  presented responses. Offline sign-out cannot retract a request already handed
  to Expo/FCM/APNs; previews therefore contain no names, care details, collection
  codes, session credentials or financial data.
- Known rate-limit rejection uses bounded exponential backoff (at most five sends).
  Timeouts, ambiguous 5xx outcomes and crashed send claims become `UNKNOWN` and are
  not automatically resent. Provider delivery itself is best effort and may still
  duplicate an alert; taps use a stable notice ID. The inbox remains authoritative.
- Receipts can be polled again safely. `DeviceNotRegistered` invalidates only the
  matching registration revision. A late failure cannot disable a rotated token.
  Jobs expire after 24 hours; provider payload TTL is one hour.

Backend/provider behavior is verified with PostgreSQL integration tests and a local
mock HTTP provider. Real FCM/APNs delivery still needs the above account/credential
setup and an end-to-end device test; native compilation or exports do not prove it.
