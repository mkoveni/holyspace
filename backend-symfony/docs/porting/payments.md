# Porting Payments to Symfony

Status: **D0–D8 done (2026-11-08). 28/28 Java routes, `make check` green.** Open: the dedicated security review
(remaining-work.md risk 7) and the deployment notes below. Source of truth: `backend-java/src/main/java/org/onechurch/payments`
(5.5k lines, 128 files, 34 test classes). Read `AGENTS.md` (Payments bullet) first.

## 1. Scope

| Capability | Routes | Symfony entry points |
| --- | --- | --- |
| Staff checkout and evidence | `GET /api/payments/providers`, `GET|POST /api/payments`, `GET /api/payments/{orderId}`, `POST .../checkout`, `.../refunds`, `.../reviews`, `.../subscription` | `PaymentsController` |
| Posting retries | `POST /api/payments/{orderId}/posting-retries` | `PaymentPostingController` |
| Donor confirmations | `GET|POST /api/payments/{orderId}/receipts`, `POST .../receipts/{receiptId}/retries` | `PaymentReceiptsController` |
| Provider settings | `GET /api/payments/settings`, `PUT /api/payments/settings/{provider}`, `POST .../{provider}/connection-check` | `PaymentSettingsController` |
| Yoco webhook administration | `GET|POST /api/payments/settings/yoco/webhooks`, `POST .../webhooks/{id}/removal`, `POST .../webhook-operations/{id}/close` | `YocoWebhooksController` |
| Provider callbacks (public) | `POST /api/payments/webhooks/{provider}` | `PaymentWebhooksController` |
| Donor self-service | `GET /api/my-giving`, `GET|POST /api/my-giving/payments[/{orderId}]`, `POST .../checkout`, `.../subscription` | `DonorGivingController` |
| Mobile handoff | `POST /api/my-giving/payments/{orderId}/mobile-checkout`, `GET|POST /api/mobile-checkouts/{token}` (public) | `MobileCheckoutController` |

Tables: V21, V22, V23, V24, V25, V26 as migrated (no schema change). Cross-context contracts used: Giving
`OnlineGivingLedger`, `OnlineFundDirectory`, `DonorIdentity`, `DonorIdentityGuard`, `DonorCheckoutGuard`, `GivingReader`;
IdentityAccess `PermissionChecker`, `AuthenticatedAccount`, `CurrentActor`, `SessionReader`, `AccountDirectory`,
`AccountNames`, `ResponsibleAccountDirectory`, `TokenCodec`; People `PersonDirectory`, `PersonEmailDirectory`;
Communications `EmailTemplates`, `EmailDeliveries`, `DeliveryReader`, plus `PaymentDeliveryEligibility` and
`PaymentReceiptTemplatePolicy` (tagged).

## 2. Status board

| # | Session | Status |
|---|---|---|
| D0 | Skeleton, `PaymentException` (+ Shared `ErrorKind::Unavailable` → 503), `PaymentHash`, AES vault and credential vault, `ProviderHttp` (Symfony HttpClient), vectors | [x] |
| D1 | Provider settings, immutable revisions, connection checks, configuration history, settings routes | [x] |
| D2 | Orders, start, resume checkout, list/get, Payfast and Yoco hosted gateways, configuration binding | [x] |
| D3 | Webhook entry point, Payfast ITN (networks, signature, merchant, validate), Yoco HMAC, notification processing | [x] |
| D4 | `PaymentPosting`, posting retries, settlement reviews | [x] |
| D5 | Refunds recorded or matched and applied through Giving | [x] |
| D6 | Subscriptions (vault, fetch/cancel through the Payfast API) | [x] |
| D7 | `/api/my-giving` and the mobile checkout handoff | [x] |
| D8 | Receipts (Communications), Yoco webhook administration, two-session tests, docs | [x] (security review still to run) |

## 3. Decision log and deviations

1. **Provider-boundary handlers.** Java runs several `tx.execute` blocks per use case so that no provider call happens
   inside a transaction and posting is separate from verification. The Symfony bus wraps one handler in one transaction,
   so these handlers follow the `Login` precedent: they are not `CommandHandler`s, inject `UnitOfWork`, and controllers
   call them directly: `ResumeCheckout`, `PrepareMobileCheckout`, `ProcessNotification`, `RecordRefund`,
   `ReconcilePayment`, `RetryPaymentPosting`, `ManageSubscription`, `CheckProviderConnection`, `RegisterYocoWebhook`,
   `RemoveYocoWebhook`. Single-transaction use cases are on the bus: `StartPayment`, `StartDonorPayment`,
   `SaveProviderSettings`, `ApprovePaymentReceipt`, `RetryPaymentReceipt`, `CompleteMobileCheckout`,
   `CloseWebhookOperation`. API tests assert the provider doubles were never called inside a transaction.
2. **Vectors.** `docs/porting/vectors/PaymentVectors.java` (run with the `maven:3.9-eclipse-temurin-25` image against
   `target/classes`) produced the `PaymentHash` vectors, a subscription-token ciphertext, a credential ciphertext with
   Jackson's JSON, and a signed monthly Payfast checkout (timezone-dependent billing date, `*`/`~`/space encoding). The
   remaining-work "AES golden vectors (subscription vault, provider credentials)" item is closed for Payments.
3. **Lock order.** Provider settings head (`bind`) → donor link (`DonorIdentityGuard::lockOwn`) → Giving donor and fund
   (`validate`); the order row before any Giving posting or refund; handoff → order → donor link for mobile completion;
   order → donor person for receipt approval; Yoco settings head for every webhook transaction.
   `PaymentConcurrencyTest` proves a duplicate callback and a competing settlement review wait on the order lock.
4. **`expectedVersion` is required** on every body (missing is 400). Java's primitive `long` defaulted it to 0.
5. **Integrity violations** are translated in the stores (`PaymentWrites`) to Java's 409 message; Java did it in a
   controller advice. Giving's own failures keep Giving's messages.
6. **Callback source address.** The ITN network check uses `Request::getClientIp()`, which honours `X-Forwarded-For`
   only from Symfony trusted proxies. Java production used `forward-headers-strategy: framework`. **Deployment: set
   `TRUSTED_PROXIES` (framework `trusted_proxies`) behind the reverse proxy, or every Payfast ITN is refused.**
7. **Stricter parsing.** Notification order ids must be canonical UUIDs (Java's `UUID.fromString` is lenient); vault
   nonces must be 12 bytes (Java always writes 12); `name_first` truncates by code point, not UTF-16 unit.
8. `WebhookOperationReader::pending` drops Java's unused actor argument. `JavaString` moved from Giving to `Shared`.
9. Tests: `.env.test` configures a Payfast sandbox and Yoco live bootstrap (as Java's `@TestPropertySource`s); the
   gateway, provider HTTP and connection probe are scripted doubles (`tests/Payments/Support`, wired in `when@test`), and
   `FailingGivingLedger` replaces Java's spied ledger. Handler security is covered by `PaymentHandlersSecurityTest`
   (spoofed or unpermitted callers touch no collaborator). OpenAPI documentation tests were skipped, as elsewhere.

## 4. Still open

- Run the dedicated security review on the Payments diff (remaining-work.md risk 7).
- A Postman/E2E parity sweep against a Payfast sandbox and a Yoco test account (no test here reaches a provider).
- Expired `payments_mobile_checkouts` rows are never purged (Java does not purge them either).
