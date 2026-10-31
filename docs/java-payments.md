# Verified online giving in Java

The Java **Giving → Online payments** workspace supports staff-assisted ZAR giving
through Payfast and Yoco hosted checkout. Payfast also supports monthly arrangements.
It does not migrate PHP records or provide paid event ticketing or a member portal.

## PHP review and design decisions

The PHP implementation correctly separates orders, verified charges, refunds and gifts;
checks provider signatures and environments; retains ambiguous outcomes; and uses the
original Yoco checkout idempotency key. Browser returns do not post gifts. These
behaviours are retained.

`ProcessPaymentNotificationHandler` and its repository-backed read model scan whole
order/charge/refund collections, and a broad gateway handles both providers. The Java
implementation uses indexed identity/reference lookups, typed records, generated jOOQ,
and focused checkout, verification, refund, subscription and settlement use cases.
Verification commits before a separate ledger-posting transaction. A temporary Giving
failure cannot discard the confirmed provider charge. No provider HTTP call runs inside
a database transaction. Posting failures remain visible and can be retried by reconciliation.

Payments owns orders, provider notifications, charges, completed refund evidence,
subscription tokens/status and settlement reviews. Giving owns immutable gifts, dated
refund adjustments, acknowledgements and pledge effects. `OnlineGivingLedger` is a
trusted published capability, never an HTTP assertion that a payment succeeded.

## Staff workflow

1. Open **Giving → Online payments**. `payments.manage` is required for all payment
   reads and checkout/subscription actions. Creating a donor/fund request through the
   screen also requires directory and Giving read access for the pickers.
2. Choose **New online gift**, a current donor, active fund, exact ZAR amount between
   R5 and R1,000,000 and a configured provider. Monthly giving uses Payfast.
3. Create the request, then choose **Continue to secure checkout**. Enter card data
   only on the provider website. Retry the same request if checkout creation is
   interrupted; the original UUID is also Yoco's idempotency key.
4. Refresh the payment after returning. Only an authenticated provider notification
   records a charge. Live charges post exactly one Giving gift per provider reference.
   Sandbox charges/refunds never change real Giving balances.
5. **Retry Giving posting** retries verified live financial evidence using `payments.manage`,
   without collecting another payment or entering settlement amounts. It is idempotent;
   unresolved errors retain the charge and show an incomplete result.
   **Review settlement** separately requires `payments.reconcile` and records a versioned
   reference, fee, bank amount and explanation for live charges. Fees do not reduce the
   donor's gift. A later refund reopens the settlement review. Simulations have no real
   bank settlement and cannot create donor confirmations.
6. Complete financial refunds in the provider dashboard. **Record completed refund**
   additionally requires `payments.refund` and never initiates a financial transfer.
   Payfast requires the exact provider reference, matched charge, date and evidence.
   Yoco refunds must first arrive through its authenticated notification; staff can
   match an unresolved notification to its charge. Conflicting/excess refunds require
   review. Partial and full refunds append negative Giving entries on their refund date.
7. **Check subscription** and **Cancel monthly giving** contact Payfast outside the
   transaction. Cancellation remains requested until provider confirmation. A charge
   already in progress can still be recorded after cancellation. Do not start a second
   monthly arrangement for the same donor/fund while the original remains unresolved.

A refund preserves the original gift and acknowledgement number, marks its original
acknowledgement VOID, and cancels existing pledge assignments with evidence. Staff may
explicitly assign the unrefunded balance again. Ordinary manual reversals cannot bypass
Payments. Statement/CSV totals include dated refund entries; existing reconciliation
batches retain their original snapshot and show later refunds in current net totals.

The register uses pages of 25 (API maximum 100), recent activity is limited to 100 entries,
and a payment detail explicitly rejects histories above 1,000 charges rather than silently
showing an incomplete financial history. Financial dates use the configured church timezone.

## Private operator configuration

For new installations, configure provider credentials through Payment settings below.
Only the encryption master key must remain in private deployment configuration. Compose
also supports these legacy environment values for pre-V22 orders and existing installations;
keep actual values private and out of frontend bundles and Git:

- `PAYMENTS_PUBLIC_URL`: public HTTPS frontend origin for checkout browser returns, without a path.
- `PAYMENTS_BACKEND_URL`: public HTTPS backend origin for provider callbacks, without a path.
  Omission retains the shared-origin deployment fallback to `PAYMENTS_PUBLIC_URL`.
- `PAYFAST_MODE`: `test` or `live`; matching `PAYFAST_MERCHANT_ID`, `PAYFAST_MERCHANT_KEY`,
  `PAYFAST_PASSPHRASE` and current comma-separated trusted `PAYFAST_ITN_IP_RANGES`.
- `PAYMENTS_TOKEN_KEY`: base64 encoding of a securely generated 32-byte key, required by
  Payfast. Tokens use authenticated AES-GCM encryption and a separate hash for lookup.
  Preserve this key securely with backups; changing it without re-encryption prevents
  existing subscriptions being managed.
- `YOCO_MODE`: `test` or `live`; matching `YOCO_SECRET_KEY` and registered
  `YOCO_WEBHOOK_SECRET` (`whsec_...`).

Callbacks are `/api/payments/webhooks/payfast` and `/api/payments/webhooks/yoco`.
Only these two POST routes bypass user authentication. Payfast still requires trusted
source IP, merchant/signature checks and provider server validation. Yoco verifies the
raw body, signing timestamp within three minutes, signature, environment and stored
checkout identity. Configure trusted proxy forwarding rather than trusting arbitrary
client IP headers. Keep server clocks synchronized. Invalid callbacks never post money;
transient provider/checkout verification failures return a retryable 503.

The local deployment intentionally has no provider credentials and shows **Not configured**.
No real provider account, charge, refund, subscription or bank settlement is exercised by
local tests. Before activation, use church-owned provider test accounts to validate hosted
checkout, callback retries, failure, partial/full refunds, monthly cancellation and an
in-flight charge; then compare the provider records with the ledger and settlement review.

Provider protocols follow [Payfast checkout/ITN](https://developers.payfast.co.za/docs),
[Payfast subscription API](https://developers.payfast.co.za/api),
[Yoco checkout creation](https://developer.yoco.com/api-reference/checkout-api/checkout/create-checkout)
and [Yoco notification verification](https://developer.yoco.com/guides/online-payments/webhooks/verifying-the-events).

## Frontend provider configuration

Java now has **Administration → Payment settings** at `/administration/payment-settings`. Access requires
`payments.configure`; ordinary payment management does not imply configuration access,
and configuration access does not grant financial reads. Flyway V22 grants the permission
to the administrator role. Refresh the app after deployment to load the new permission.

For each provider:

1. Choose Test or Live and enter both public HTTPS origins: **Frontend website URL**
   for browser returns (for example, `https://church.example`) and **Backend API URL**
   for notifications (for example, `https://api.church.example`). Copy the displayed
   backend callback URL into the provider setup. Payfast's trusted notification networks are
   under Advanced callback protection.
2. Enter replacement credentials and save with **Enable new checkouts** switched off.
   Blank fields preserve that environment's values. Saved secrets are never displayed
   or returned by the settings API; the screen only indicates whether a value exists.
   For Yoco, use the environment's secret API key (`sk_test_...` or `sk_live_...`).
   The webhook signing secret is a separate `whsec_...` value returned when registering
   the callback URL with Yoco. Copy the entire value, including any trailing `=` characters;
   adding `whsec_` to an API key does not create a signing secret. Connection checks
   identify malformed credentials without returning the saved values.
3. Choose **Test saved connection**. This does not create a checkout or collect money.
   Payfast uses its signed [ping endpoint](https://developers.payfast.co.za/api).
   Yoco uses [List webhooks](https://developer.yoco.com/api-reference/checkout-api/webhooks/list-webhooks)
   to check API access and callback registration for the selected environment. These
   checks do not prove merchant checkout keys, callback-secret correctness, delivery
   reachability or settlement. Complete the provider pilot before live use.
4. After a successful check, enable new checkouts, optionally offer monthly giving
   through Payfast, enter a reason and save. Activation requires a successful check
   for those exact saved connection details within the previous 24 hours. Editing
   connection details creates a new revision and requires another check.

Test and live credentials are independent. A settings change uses optimistic versions,
so concurrent edits cannot silently replace each other. Connection checks run outside
transactions and cannot approve a configuration changed while the request was running.
The latest 100 changes/checks record actor, time, environment, field names and the reason;
secret values and raw provider responses are excluded.

New orders pin an immutable encrypted credential revision. Changing environments,
disabling new checkouts or disabling monthly offers does not replace existing order
credentials or cancel subscriptions. Older checkouts, callback authentication and
subscription management use the original revision. Keep the corresponding provider
keys, webhook subscriptions, public endpoint and merchant account valid until those
payments/subscriptions finish. The screen does not revoke keys or rotate credentials at
Payfast/Yoco. Revoking a historical key externally can still prevent those operations.
Existing orders created before V22 continue to use their original deployment credentials;
retain those environment values for as long as those older orders/subscriptions need them.

`PAYMENTS_TOKEN_KEY` remains a deployment secret; it encrypts both credentials and
subscription tokens using authenticated AES-GCM encryption. It is never returned to or
changed from the frontend. Keep its private configuration and database backups together
securely. The local installation has a generated key in ignored, owner-readable
`backend-java/.env`; the Docker build excludes that file. Losing/replacing this key prevents
saved credentials and subscription tokens from being decrypted. Provider credentials can
now be managed through the screen without application restarts.

Configuration responses never expose saved credential values. Payfast's hosted checkout
still sends its required public merchant ID/key in the browser form, as required by its
integration protocol. The passphrase, Yoco API key, webhook signing secret and encryption
master key remain on the backend.

Frontend and backend addresses may use separate domains or HTTPS development tunnels.
Yoco callbacks must reach the backend, for example
`https://api.church.example/api/payments/webhooks/yoco`; checkout return URLs point to
`https://church.example/payments`. A local backend tunnel must forward to port 8082.
V23 adds a separate backend origin to immutable configuration revisions. Existing
revisions preserve their original shared-origin behaviour; editing either URL creates
an unchecked revision that must be saved disabled and tested before activation.

## Payment operations and confirmations (V24)

The payment register separates provider confirmation from Giving posting and bank settlement.
Summary `givingStatus` is `SIMULATION`, `AWAITING_PAYMENT`, `POSTING_PENDING` or `POSTED`;
charge views also expose `settlementStatus` (`NOT_APPLICABLE`, `NEEDS_REVIEW`, `MATCHED`).
Test charges are visibly simulated, never enter Giving totals and never need bank matching.
The explicit posting retry endpoint is `POST /api/payments/{id}/posting-retries` with the
current order `expectedVersion`. It uses retained verified evidence and does not call a
provider checkout or require reconciliation permission. A failure reports `complete=false`.

Under **Administration → Payment settings → Yoco**, save the API key and backend origin,
then load subscriptions for the selected environment. **Register saved callback** registers
that backend endpoint and saves Yoco's returned signing secret directly into a new encrypted
revision. The browser never receives the secret. Keep new checkouts disabled until a fresh
connection check succeeds. Old immutable order configurations remain available.

Review an obsolete subscription before removing it. The application blocks removal when
existing payments may depend on its callback, including legacy payments without a mapped
configuration. Removal invalidates the saved connection check and disables new checkouts
for that environment. Unknown removals prevent activation and new checkouts until resolved.
Changes require `payments.configure`, optimistic versions, idempotency UUIDs and a reason.

A registration request is sent to Yoco at most once. An uncertain outcome stays `UNKNOWN`;
inspect the provider list before creating another subscription. A successful registration
that races with a settings edit stays `READY`, with its secret retained encrypted, until
**Finish saving registration** applies it to matching backend/API-key settings. Retry the
same request. An interrupted `STARTED` operation can be closed after five minutes, following
explicit provider review. Closing makes no provider mutation and requires a new connection
check. Operation and audit responses never contain signing secrets or saved API keys.

**Donor confirmation** is available for posted live contributions. It has its own
`payments.receipts.send` permission, together with `payments.manage`. Choose one of the
donor's current People email contacts, preview the published `payments.receipt` template,
confirm that the address belongs to the donor and record verification evidence. Approval
freezes the recipient, rendered HTML and template revision and queues Communications delivery.
Repeated approval cannot send another confirmation for the same charge. Known refunds,
unposted charges and simulations cannot be approved. The confirmation is not a tax certificate
and does not replace Giving's numbered acknowledgement workflow.

The template is editable in **Communications → Templates**. It retains name, amount, date,
provider and reference variables and the non-tax-certificate wording. Communications
rechecks the current approver's live permissions, recipient contact, source charge and refund
evidence before dispatch. Changed eligibility blocks the send. Definite failures/blocked
sends can be retried with a reviewed reason and the delivery version; uncertain sends cannot
be automatically resent. Approved content stays frozen on retry. Delivery status distinguishes
queued, captured in the local inbox, SMTP accepted, confirmed delivered and uncertain outcomes.
Approved confirmation history remains readable after the original contact or payment changes.

Donor self-service remains a separate subsequent phase; these operations support the existing
staff-assisted checkout flow.

## Donor self-service

`/my-giving` is a separate, authenticated donor workspace. Donors do not need or
receive staff finance permissions. Accounts without a verified link see a clear
verification request and no financial data. The login landing page sends accounts
without an administrative workspace to My giving.

Administrators use **Administration → Donor access** to verify a user account
against a People record, record identity verification evidence, revoke access, or
verify and restore the same link. Both `identity.manage` and `giving.manage` are
required. Account and person links are unique. They cannot be reassigned to another
person through this flow, including after revocation; incorrect identity evidence
requires a separately reviewed correction. Email matching, household membership,
volunteer account links and ministry leadership never confer donor access.

Giving owns the verified financial identity and explicitly opted-in online fund
catalogue. Identity Access publishes live authenticated account resolution. Payments
uses these published contracts and reuses its durable hosted-checkout, webhook,
subscription and Giving-posting workflows. New order requests derive the donor on
the server. Command guards lock and recheck the verified link before committing
changes. Revoking a link does not erase contributions, stop provider verification,
or silently cancel an existing monthly arrangement.

- `GET /api/my-giving`: own profile, active offered funds, available providers and
  independently paged contributions/payment activity. Unlinked accounts receive an
  empty response with `donor: null`. Cash/bank gifts recorded by staff are included
  when they belong to the verified person. Test payments never appear as gifts.
- `POST /api/my-giving/payments`: idempotent request UUID, selected fund, exact ZAR
  minor units, provider and monthly choice; accepts no donor identity assertion.
- `GET /api/my-giving/payments/{id}`: own payment, verified charges, refunds and
  confirmation status plus the frozen original confirmation content when issued. Excludes staff notes, bank settlement, tokens and contact
  addresses. Unknown and other-donor order IDs return 404.
- `POST /api/my-giving/payments/{id}/checkout`: resume the same provider checkout.
  Donor checkouts return to `/my-giving?order=...`; callbacks retain the separately
  configured backend origin. Browser returns never confirm a charge.
- `POST /api/my-giving/payments/{id}/subscription`: versioned check/cancellation of
  own monthly giving. Cancellation remains requested until provider confirmation.
  In-flight charges remain verifiable. Provider calls stay outside transactions.
- `GET /api/giving/donor-access` and `PUT /api/giving/donor-access/{accountId}`:
  protected administration of verified links, including optimistic versions and
  immutable verification/revocation history.
- `PUT /api/giving/online-funds/{fundId}`: requires `giving.manage` and the current
  fund version. Active funds are not automatically offered online. Withdrawing a
  fund blocks new donor requests while existing checkout/subscription flows remain
  accessible.

Personal financial responses use `Cache-Control: no-store`. The browser retains
only an opaque pending request UUID in session storage for recovery, scoped to the
account and cleared on logout. It does not cache personal history or payment tokens.
An uncertain create request freezes the reviewed intent and reuses its UUID.

### Native app browser handoff

The member app prepares `POST /api/my-giving/payments/{orderId}/mobile-checkout`
with its authenticated donor session. The returned five-minute URL contains only
an opaque one-use capability; the account token is never sent in a URL. The public
GET displays a static continuation page; its POST rechecks the bound live session,
account, donor link and payable order before consuming the capability and opening
an allowlisted provider form or redirect. Checkout payloads are encrypted and
capabilities are hashed. Used/expired/revoked links display a 410 page with guidance
to prepare a fresh handoff for the same payment. Provider callbacks alone determine
payment status. See `member-mobile-api-review.md` for the complete boundary review.
