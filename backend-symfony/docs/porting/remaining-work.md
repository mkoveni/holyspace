# Remaining Java-to-Symfony port: technical assessment

Date: 2026-11-07. Java (`../backend-java`) is the source of truth. This assessment covers the four contexts that do not yet
match Java. The other six (Children Ministry, Events, Giving, IdentityAccess, People, Shared) are ported route for route.

How this was measured: every Java `@*Mapping` route was compared with `bin/console debug:router`. Table and contract
availability was checked against `migrations/` and `src/*/Application/Contract`.

## Status board

| # | Session | Status |
|---|---|---|
| A1 | Inbox: `MemberNotifications`, preferences, inbox routes, `GET /api/member/notifications/{id}`, Events seam replaced | [x] 2026-11-07 |
| A2 | Push devices and preferences, token cipher, push routes | [x] 2026-11-07 (Java-captured cipher vector still open) |
| A3 | Push queue, processor, Expo transport, scheduler; `publish` enqueues push jobs | [x] 2026-11-07. **Item A complete:** Communications matches Java's 17 routes |
| B (S1–S6) | Communities (see `communities.md`) | [x] 2026-11-07. **Item B complete:** 31/31 routes, Events movement veto live, Java-captured `CommunityHash` vectors, two-session tests |
| V1–V8 | Volunteering (see `volunteering.md`) | [x] 2026-11-07. **Item C complete:** 41/41 routes; member duty notices via the inbox, reminders via Communications email |
| D0–D8 | Payments (see `payments.md`) | [x] 2026-11-08. **Item D complete:** 28/28 routes, Java-captured `PaymentHash`, vault and checkout-signature vectors, two-session tests |

### A1 log (2026-11-07)

- **Delivered.** Domain `MemberNotice` (fixed wording, topic, destination). Contracts `MemberNotifications` +
  `MemberNotificationKind`, `InboxReader`, `InboxWriter`, `MemberInbox`, `MemberNotification`,
  `MemberNotificationPreference`. Commands `SetMyNotificationRead`, `SetMyNotificationPreference`; queries
  `ListMyNotifications`, `GetMyNotification`, `GetMyNotificationPreferences`. `DbalMemberInboxStore`,
  `MemberInboxController`. Routes `GET /api/member/notifications`, `GET /api/member/notifications/{id}`,
  `PUT /api/member/notifications/{id}/read`, `GET /api/member/notification-preferences`,
  `PUT /api/member/notification-preferences/{topic}`.
- **Seam closed.** `Events\Infrastructure\PendingMemberBookingUpdates` replaced by `InboxMemberBookingUpdates`.
- **Tests.** `MemberInboxApiTest` (port of `MemberInboxApiIntegrationTest` plus the resolve route and source-key rules),
  `MemberInboxHandlersTest` (port of the five inbox `*HandlerSecurityTest`s: no live session reaches no store; commands
  recheck after writing), `MemberEventsApiTest::testBookingChangesReachOnlyTheMembersOwnInbox`. `make check` green
  (1481 tests).
- **Deviations.** (1) `GET /api/member/notifications/{id}` lives on `MemberPushApi`/`PushReader` in Java; here it is
  `InboxReader::notification` on `MemberInboxController`, because push is not ported yet. Like Java it does not filter
  hidden notices and does not mark read. (2) `expectedVersion` on the preference PUT is required (missing is 400), per
  AGENTS.md; Java's primitive `long` defaulted a missing value to 0. (3) `publish` does not enqueue push jobs yet:
  Java calls `PushWriter::enqueue` for a newly inserted row. A3 adds this inside the same store, so nothing is
  stubbed in between (push is simply not delivered, as when `PUSH_ENABLED=false`).
- **Next session notes (A2).** Read `PushDevice`, `PushDeliveryPolicy`, `AesPushTokens`, `JooqPushDeviceStore`,
  `MemberPushController`, `registermypushdevice`, `revokemypushdevice`, `setmypushpreference`, `getmypushsettings` and
  `MemberPushApiIntegrationTest`. Tables `communication_push_devices`, `communication_push_preferences` (V30). The
  token cipher must read Java rows: `base64(nonce12):base64(ciphertext‖tag16)`, AAD `holyspace-push-v1`, key
  `PUSH_TOKEN_KEY` (base64). `GET /api/member/push` returns `PushReader.MemberPushSettings` and needs the current
  session id (IdentityAccess `SessionReader`).

### A2 log (2026-11-07)

- **Delivered.** Domain `PushDevice` (private constructor, `register`/`reconstitute`, `requireValidRegistration`,
  `lease`, `heldBy`, `renew`, `revoke`; `__debugInfo` shows only id and status) and `Repository\PushDeviceRepository`.
  Contracts `PushTokens`, `PushSettings`, `PushWriter` (preference only for now), `PushReader`, `PushDeviceView`,
  `PushPreference`, `MemberPushSettings`. Commands `RegisterMyPushDevice`, `RevokeMyPushDevice`, `SetMyPushPreference`
  (Java's `PushDeviceActions` logic lives in the two device handlers, since the bus already owns the transaction);
  query `GetMyPushSettings`. `Infrastructure\Push\AesPushTokens`, `ExpoPushSettings`, `DbalPushDeviceStore`
  (`PushReader` + `PushDeviceRepository`), `DbalPushQueueStore` (`PushWriter`; the queue joins it in A3).
  `MemberPushController`: `GET /api/member/push`, `PUT|DELETE /api/member/push/devices/{id}`,
  `PUT /api/member/push/preferences/{topic}`. Env `PUSH_ENABLED` (default false), `PUSH_PROJECT_ID`,
  `PUSH_TOKEN_KEY`; `.env.test` uses the Java test key and project.
- **Register flow (as Java).** Live account and the caller's own session (403 without one); input rules; 409 unless
  push is enabled with a key and the request names the configured project (compared case-insensitively). Session
  must belong to the caller and be unexpired (403). The lease is the earlier of the session expiry and 30 days.
  `ensure` retires the same account's other ACTIVE registration of the token and inserts the draft unless the id
  exists; a token ACTIVE on another account is a 409 (unique index `communication_push_active_token_idx`). The locked
  row's installation secret must match (`hash_equals`, 403); a different account needs the previous session to have
  ended (409). The revision moves only when account, session, token, project, platform or active state changes.
  Lock order: the device row only.
- **Tests.** `MemberPushApiTest` (the registration, preference, revocation and account-change parts of Java
  `MemberPushApiIntegrationTest`, plus input rules, lease, token rotation and reinstall); `AesPushTokensTest`;
  `PushDeviceTest`. `make check` green (1501 tests).
- **Deviations.** (1) The cipher vectors in `AesPushTokensTest` come from an independent AES-GCM implementation
  (Python `cryptography`), not from Java: no JDK compiler was available (the local `holyspace-java-api` images are
  JRE-only). Before a mixed deployment, encrypt one token with Java `AesPushTokens` and add it as a vector. (2)
  `GET /api/member/push` without a session actor is a 403; Java's `orElseThrow()` would have been a 500. (3) An
  invalid `PUSH_PROJECT_ID` leaves push unavailable instead of failing startup as Java's `UUID.fromString` does. (4)
  Symfony appends `private` to the `no-store` cache header. (5) Inbox and push responses now send `Cache-Control:
  no-store` exactly as Java's two controllers do (A1 previously used the default trio).
- **Next session notes (A3).** Read `PushDeliveryProcessor`, `PushQueue`, `PushTransport`, `ExpoPushTransport`,
  `PushScheduler`, `PushDeliveryPolicy`, `JooqPushQueueStore` (`enqueue` and the queue methods) and the dispatch half
  of `MemberPushApiIntegrationTest`. Add `PushWriter::enqueue` and call it from `DbalMemberInboxStore::publish` only
  when the notice row was inserted (`ON CONFLICT ... DO NOTHING RETURNING id`). Jobs carry `device_revision` and
  `preference_version`; dispatch cancels a job whose device revision, preference version, account status or session
  no longer match. Transport calls happen outside the transaction (the Java test asserts `inTransaction` is false).
  Add a `RecurringMessage` next to the email dispatcher in `src/Schedule.php` (`PUSH_POLL_MS`, default 15 s).

### A3 log (2026-11-07)

- **Delivered.** Domain `PushJob` (states, claimed-job record), `PushDeliveryPolicy` (backoff 30 s doubling to 1 h,
  five attempts, 24-hour expiry, 15-minute receipt delay, 120-second claim lease) and `Repository\PushQueue`.
  Contracts `PushTransport` + `PushTransportResult`; `PushWriter::enqueue`. `Application\PushDeliveryProcessor`
  (`runDue`, `dispatch`). `DbalPushQueueStore` now implements `PushQueue` too. `Infrastructure\Push\ExpoPushTransport`
  (Symfony HttpClient; HTTPS only, plain HTTP only to localhost; `PUSH_EXPO_URL`, `PUSH_ACCESS_TOKEN`).
  `app:communications:dispatch-push` console command, scheduled every 15 seconds next to the email worker.
  `DbalMemberInboxStore::publish` queues push jobs only when the notice row is newly inserted (`RETURNING id`), so a
  retry queues nothing, and a rollback of the publishing transaction removes both.
- **Dispatch rules (as Java).** Claim (`FOR UPDATE SKIP LOCKED`) in one transaction; the provider is called outside any
  transaction; the outcome is recorded in another, only if the claim still holds. The job is cancelled
  (`ELIGIBILITY_CHANGED`) unless the device is ACTIVE at the queued revision with an unexpired lease, the topic is
  still opted in at the queued version, the project still matches, the account is active and the device's session is
  live and the account's. A possible send (`UNKNOWN`) is never retried; rate limits are. `DeviceNotRegistered` marks
  only the queued device revision INVALID. Recovery: expired SENDING claims become UNKNOWN (`LEASE_EXPIRED`), expired
  CHECKING claims go back to TICKET, devices past their lease are revoked.
- **Tests.** `MemberPushDispatchTest` (the dispatch half of `MemberPushApiIntegrationTest`: atomic deduplicated outbox,
  provider outside transactions, receipt after ticket, opt-out and revocation cancel, disabled account and
  expired/revoked sessions, token rotation and `DeviceNotRegistered`, ambiguous send not resubmitted, rate-limit retry
  and attempt cap, inbox/push independence, account change, crash recovery, 24-hour expiry and lease revocation);
  `ExpoPushTransportTest` (`MockHttpClient`, no network); `DispatchCommandTest` extended. `make check` green
  (1534 tests). Route diff: Communications 17/17.
- **Deviations.** (1) The poll interval is a fixed 15 seconds in `src/Schedule.php` (Java's `PUSH_POLL_MS` default);
  it is not configurable. (2) The response size cap (64 KiB) is checked after reading the body; Java stopped reading
  at 64 KiB + 1. (3) `ExpoPushTransport` has no Java unit tests to port; `ExpoPushTransportTest` derives its cases from
  the Java source. (4) The Java-captured cipher vector from A2 is still open.

## 1. Summary

| Item | Java size | Routes missing | Handlers | Java tests | Est. sessions | Risk |
|---|---|---|---|---|---|---|
| A. Communications member inbox and push | ~1.2k of 3.3k lines | 9 / 17 | 9 | ~10 of 26 classes | 3 | Medium |
| B. Communities | 3.5k lines, 101 files | 31 / 31 | 32 | 35 classes, ~1k lines | 6 | Medium |
| C. Volunteering (beyond the staffing slice) | ~4.1k of 5.4k lines | 41 / 41 | 42 | 11 classes, ~0.7k lines | 8 | Medium–High |
| D. Payments | 5.5k lines, 128 files | 28 / 28 | 26 | 34 classes, ~2.1k lines | 9 | **High** |
| **Total** | | **109** | **109** | | **~26** | |

Session sizes follow the existing plans (S/M/L as in `giving.md`). One session is one bounded slice ending with `make check`
green.

**Good news shared by all four.** Every table already exists in the Symfony migrations (V14, V18, V21, V22, V24, V26, V29,
V30), so no schema work is needed beyond parity checks. All cross-context contracts these contexts consume already exist
in Symfony, **except `Communications\MemberNotifications`** (built in A).

## 2. Dependency order

```
A (inbox/push) ──► replaces Events PendingMemberBookingUpdates
      │
      ├──► C (Volunteering: member invitations use MemberNotifications)
      └──► B (Communities: does not need A, but notices share delivery patterns)

D (Payments) ── independent: needs Giving G7/G8, Communications email, IdentityAccess, People (all ported)
```

Recommended order: **A first** (small, unblocks the only `PLACEHOLDER` seam and Volunteering). Then B, C and D can run in
parallel because they touch disjoint directories. Give each its own plan file and status board, and never let two writers
touch shared files (`config/services.yaml`, `deptrac.yaml`, `ApiExceptionListener`) at the same time. Serialize those
edits or merge them carefully.

## 3. Item A — Communications member inbox and push

**Scope.** Commands `registermypushdevice`, `revokemypushdevice`, `setmynotificationpreference`, `setmynotificationread`
and `setmypushpreference`. Queries `getmynotification`, `getmynotificationpreferences`, `getmypushsettings` and
`listmynotifications`. Domain `MemberNotice`, `PushDevice` and `PushDeliveryPolicy`. Application `PushQueue`,
`PushDeliveryProcessor` and `PushDeviceActions`. Published contract `MemberNotifications`.

**Routes.** `GET /api/member/notifications`, `GET|PUT /api/member/notifications/{id}[/read]`,
`GET /api/member/notification-preferences`, `PUT .../{topic}`, `GET /api/member/push`, `PUT|DELETE
/api/member/push/devices/{id}`, `PUT /api/member/push/preferences/{topic}`.

**Tables.** `communication_member_notifications` and `communication_member_preferences` (V29).
`communication_push_devices`, `communication_push_preferences` and `communication_push_deliveries` (V30).

**Infrastructure.**
- `ExpoPushTransport` becomes a Symfony HttpClient adapter (`PUSH_EXPO_URL`, `PUSH_ACCESS_TOKEN`, `PUSH_PROJECT_ID`,
  `PUSH_ENABLED`).
- `PushScheduler` becomes a new `RecurringMessage` in `src/Schedule.php` (Java polls every `PUSH_POLL_MS`, default
  15 s).
- `AesPushTokens` becomes an AES-256-GCM adapter keyed by `PUSH_TOKEN_KEY`.

**Risks and decisions.**
- **Ciphertext compatibility.** Java stores `base64(nonce12):base64(ciphertext‖tag16)` with AAD `holyspace-push-v1`. PHP
  `openssl_encrypt` returns the tag separately, so the adapter must append it on encrypt and split it on decrypt. Add a
  golden vector test generated by Java, otherwise existing device tokens become unreadable.
- Keep `CommunicationsIndependenceTest` green, because the inbox must not import other contexts.
- Replace `Events\Infrastructure\PendingMemberBookingUpdates` with a real adapter over `MemberNotifications`. Delete the
  placeholder rather than extending it (AGENTS.md rule).

**Sessions.**
- A1: inbox domain, preferences, `MemberNotifications` contract, inbox routes, and the Events seam replacement. Size M.
- A2: push devices and preferences, the token cipher with its golden vector, and push routes. Size M.
- A3: push queue, processor, Expo transport, scheduler, and parity sweep. Size M.

## 4. Item B — Communities

**State.** `docs/porting/communities.md` has a plan (C1–C6, unchecked). About 16 uncommitted files already exist in
`src/Communities` (domain, create/update community, repository, audit writer). The first step is to record them on the
status board.

**Scope.** 28 command and 34 query classes (32 handlers). Services `CommunityManagement`, `CommunityMessaging`,
`GroupMeetings`, `CommunityViews` and `MemberCommunityViews`. `CommunityHash` (request hashing). Published contracts
(4).

**Routes.** All 31: directory, members, leadership, history, follow-ups, meetings, roster, guests, finish, notices,
notice review, contacts, deliveries, and `/api/member/communities` with `/api/member/group-meetings`.

**Tables.** `communities`, `community_memberships`, `community_leadership`, `community_meetings`, `community_roster`,
`community_history`, `community_requests`, `community_followups` and `community_notices` (V18).

**Cross-context work.**
- Events already publishes `CommunityCalendar`, `CommunityScheduleReader`, `OccurrenceMovementPolicy` and
  `OccurrenceScheduleListener` (E9). Communities must implement `CommunityOccurrenceMovement` and
  `CommunityScheduleListener` and wire them as tagged services. After that, Events reschedules gain the movement veto
  that is currently absent (events.md E3 deviation).
- Communications: `CommunityDeliveryEligibility` and `CommunityTemplatePolicy`, following the Children messaging pattern.
- IdentityAccess: `MemberIdentity` and `AccountNames`. People: `PersonDirectory` and `PersonEmailDirectory`. All exist.

**Risks.**
- `CommunityHash` must be byte-compatible with Java for idempotent requests (`community_requests`). Reuse the Giving
  UTF-16 length-prefix approach and add Java-captured vectors.
- Meeting move and veto interplay with Events locks. Document the lock order (community before meetings, and how it fits
  Events' event → series → venues → occurrences order) and add a two-session test.
- Half-open membership intervals and "current member" filtering must match Java exactly.

**Sessions.** Follow `communities.md` C1–C6. C2 and C4 are size L, the rest M.

## 5. Item C — Volunteering

**State.** The P2a/P2b staffing slice is ported: readers, `StaffingDirectory`, `ActivityRegistry`, readiness and
fingerprint. These are read-only and have no write path or routes. Events and Children Ministry depend on it, so
extending it must not change those contracts.

**Scope.** 50 command and 32 query classes (42 handlers). Services `TeamManagement`, `VolunteerProfiles`,
`RosterPlanning`, `ScheduleDates`, `ReminderReview`, `MemberVolunteerActions` and `MemberVolunteerViews`. Domain
aggregates `Team`, `TeamMembership`, `ServingRole`, `RoleApproval`, `Volunteer`, `Credential`, `CredentialType`, `Shift`,
`Assignment` and `Invitation`. Three repositories.

**Routes.** All 41 under `/api/volunteering` and `/api/member/volunteering`.

**Tables.** All 16 `serving_*` tables (V14). The readers already query most of them.

**Cross-context work.**
- Communications: email deliveries for reminders (`VolunteerReminderEligibility`, `VolunteerTemplatePolicy`), and
  `MemberNotifications` for invitations, which **needs A1**.
- IdentityAccess: `AuthenticatedAccount` and `ResponsibleAccountDirectory` (both exist).
- Events: the shift ↔ occurrence linkage goes through `ActivitySource` and `ServingActivityLocks`. A write path now
  exists, so the stale-shift-on-reschedule seam can be tested end to end. Events E3 noted it was only proven through
  `ActivityRegistry`.

**Risks.**
- **The largest handler count.** Recurring shift generation (`ScheduleDates`, `shifts/bulk`, `shifts/preview`) and
  availability rules or exceptions carry most of the date and time-zone logic. Port the Java tests first.
- Assignment status machine and invitation expiry (`/invitations/expire`). Check whether Java runs expiry on a schedule
  or only when called (no `@Scheduled` exists, so it is called through the endpoint).
- Credential validity windows interact with the existing `ValidityWindow` and readiness code. Reuse it and do not fork
  it.
- Java has relatively few tests here (11 classes), so add handler tests for permission, stale version and
  audit-on-success, as was done for Events.

**Sessions.**
- V1: teams, roles and memberships. Size M.
- V2: role approvals and revocation. Size S.
- V3: volunteer profiles, credential types, credentials and account linking. Size M.
- V4: availability. Size S.
- V5: shifts, bulk creation and preview. Size L.
- V6: assignments and status events. Size M.
- V7: invitations and member volunteering (needs A1). Size M.
- V8: reminders and deliveries, then a hardening sweep. Size M.

## 6. Item D — Payments

**Scope.** 34 command and 16 query classes (26 handlers). `PaymentPosting`, `PaymentHash`, `PaymentReceiptPreview` and
`YocoWebhookManagement`. Domain `PaymentOrder`, `Charge`, `Refund`, `SettlementReview`, `MobileCheckout`,
`PaymentReceipt`, `ProviderConfiguration`, `ProviderCredentials`, `ProviderSettings`, `ConfigurationCheck` and
`WebhookOperation`.

**Routes.** All 28: `/api/payments` (orders, checkout, refunds, reviews, subscription, receipts, posting retries,
providers, settings, connection check, Yoco webhook management), `/api/payments/webhooks/{provider}`, `/api/my-giving`,
and `/api/mobile-checkouts/{token}`.

**Tables.** `payments_orders`, `payments_charges`, `payments_refunds`, `payments_notifications`, `payments_reviews` and
`payments_history` (V21). `payments_provider_settings` and `payments_configuration_*` (V22). `payments_webhook_operations`
and `payments_receipts` (V24). `payments_mobile_checkouts` (V26).

**Providers.**
- **PayFast**: hosted process redirect, ITN callback with signature, passphrase, IP-range check and server-side
  `validate` round trip, `ping`, and the subscriptions API.
- **Yoco**: hosted checkouts and webhook registration or removal (`YOCO_SECRET_KEY`, `YOCO_WEBHOOK_SECRET`).

Java uses a `ProviderHttp` abstraction (`JdkProviderHttp`). Port it to Symfony HttpClient with `MockHttpClient` in tests.
No test may make a network call.

**Cross-context work (all exist).** Giving `OnlineGivingLedger`, `OnlineFundDirectory`, `DonorIdentity`,
`DonorIdentityGuard`, `DonorCheckoutGuard` and `GivingReader`. Communications email contracts for receipts
(`PaymentDeliveryEligibility`, `PaymentReceiptTemplatePolicy`). IdentityAccess `TokenCodec`, `SessionReader`,
`ResponsibleAccountDirectory` and `PermissionChecker`. People `PersonDirectory` and `PersonEmailDirectory`.

**Risks. This is the highest-risk item: real money and external callers.**
1. **Encrypted data compatibility.**
   - `AesSubscriptionVault` (`PAYMENTS_TOKEN_KEY`) and `EncryptedPaymentCredentials` use AES-GCM with the format
     `base64(nonce):base64(ct‖tag)`.
   - The PHP adapters must read rows written by Java. Add Java-generated golden vectors for both. Check whether
     credentials use AAD or a key-revision prefix.
   - The master key stays deployment-owned (CONTEXT-MAP).
2. **Webhook authenticity.**
   - Port PayFast ITN validation exactly: field order, URL encoding, passphrase, the IP allow-list
     (`PAYFAST_ITN_IP_RANGES`) and the server-to-server validate step.
   - Port Yoco signature verification with a constant-time compare.
   - The callback is an explicit trusted entry point with no session. Make sure the Symfony firewall and `access_control`
     do not block it or, worse, open neighbouring routes.
3. **Transaction boundaries.** "Provider network calls occur outside transactions. Durable verification and ledger
   posting use separate transactions." Port each Java `tx.inTransaction` boundary one to one. A provider call inside a
   DBAL transaction is a defect. `PaymentPosting` plus `posting-retries` must stay idempotent against `OnlineGivingLedger`
   (Giving G8 already guards duplicates).
4. **Hash parity.** `PaymentHash` needs the same Java-captured vectors treatment that Giving and Events still owe (D5).
5. **Mobile checkout handoff.** The one-time token, expiry and origin checks (`ConfiguredMobileCheckoutOrigin`,
   `PAYMENTS_PUBLIC_URL` and `PAYMENTS_BACKEND_URL`) are security-sensitive. Port their tests verbatim.
6. **Concurrency.** Parallel duplicate ITNs, refund versus posting, and two reviews on one order need two-session tests
   using the Giving `ParallelSession` helper.
7. Run a dedicated security review at the end (`/security-review`).

**Sessions.**
- D0: foundation. Skeleton, deptrac, `PaymentException`, `PaymentHash` with vectors, the AES adapters with vectors, the
  `ProviderHttp` port, and the exception listener mapping. Size M.
- D1: provider settings, configuration revisions, connection checks, history and settings routes. Size M.
- D2: payment orders, start, checkout and list/get using the PayFast and Yoco hosted gateways. Size L.
- D3: webhook entry point, PayFast ITN, Yoco signatures, notification processing and charge verification. Size L.
- D4: posting to Giving, posting retries and the reconcile/review flow. Size M.
- D5: refunds recorded and applied through Giving. Size M.
- D6: subscriptions (vault and `managesubscription`). Size M.
- D7: donor self-service (`/api/my-giving`) and mobile checkout handoff. Size M.
- D8: receipts (Communications email), Yoco webhook administration, hardening, and the security review. Size M.

## 7. Cross-cutting items to close the port

- **Java-captured hash vectors** for `GivingHash` and the Events hashes (`CommunityHash` done in S6, `PaymentHash`
  and the Payments AES vault/credential ciphertexts done in D8 with `docs/porting/vectors/PaymentVectors.java`; reuse
  that approach with the `maven:3.9-eclipse-temurin-25` image), plus the push AES golden vector. These block any mixed Java/Symfony deployment (D5 in
  `events.md` and `giving.md`).
- **OpenAPI documentation tests** (`*DocumentationIntegrationTest`) were skipped everywhere. Decide once whether
  Symfony needs an OpenAPI parity check.
- **Optional:** `GET /api/children/safeguarding-follow-up` (children-ministry.md, "not ported").
- **Postman/E2E parity sweep** across all contexts once B–D land, then retire the Java backend per environment (Events
  D5 routing decision).

## 8. Effort and staffing

About 26 sessions in total. With A first and then B, C and D in parallel (three writers on disjoint directories), the
critical path is A (3) + D (9), roughly 12 sequential sessions. Payments is the critical path and the riskiest piece, so
it should start as soon as possible. Its D0 vectors work can begin even before A finishes.
