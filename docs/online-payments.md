# Online giving and paid events

This feature supports one church in South Africa, using ZAR. Payfast and Yoco provide hosted checkout for one-off giving and event tickets. Monthly recurring giving uses Payfast; Yoco does not currently support recurring billing.

## For members

1. Open **My church → Giving and tickets**. Your account must be linked to your person, have `member.portal`, and have completed authenticator verification.
2. Under **Give online**, choose a fund enabled by church staff, enter a rand amount of at least R5.00, and select an available provider. To give monthly, choose Payfast and select **Give this amount every month until I cancel**. The first gift is charged during checkout; future payments use a monthly provider subscription.
3. Choose **Continue to secure checkout**. Enter payment details only on the provider’s hosted website.
4. On returning, choose **Refresh payments**. A successful return page does not prove payment. Your history shows verified charges separately from pending requests.
5. If checkout creation is interrupted, use **Retry same payment request** or **Resume this checkout**. The same request reuses the original order and Yoco idempotency key. Do not create another monthly setup while one for that fund is unresolved.
6. Use **Cancel monthly giving** to request cancellation of future Payfast charges. Cancellation is recorded as complete only after Payfast confirms it. A charge already in progress can still arrive and will be recorded. If cancellation is unconfirmed, refresh and retry the cancellation or ask staff to check Payfast.

Under **Paid events**, choose an event and **Book with Payfast/Yoco**. A place is reserved for 15 minutes. Payment must be verified before the reservation expires and while the occurrence remains available. A late payment is retained for staff refund review without confirming a place. Ticket payments do not appear in personal giving statements. There are no paid waitlist charges or automatic paid promotions.

**Sandbox** means a test payment. It never posts a real gift or confirms a real ticket. A temporary test ticket reservation is released when the test payment is verified.

## For staff

Open **Giving → Online payments**. Access requires `payments.manage`. The initial migration grants this permission to existing named administrator roles; other roles require explicit assignment.

To enable funds, staff also need `giving.manage`. Select **Funds available for online giving** and **Save online giving funds**. Removing a fund stops new online giving requests; previously created orders retain their original allocation.

To sell tickets, staff need `events.manage`. Open the event’s registration section, select the occurrence, configure its capacity/open status, enter **Ticket price (ZAR)**, and choose **Save ticket price**. Leave the price blank for free registration. Paid, open occurrences appear in Giving and tickets for linked, verified members. The price cannot change while a payment reservation remains active. Confirmed attendees and active reservations both consume capacity. Staff registration and form approval cannot bypass a configured ticket price.

The payment history shows verified charges, refund totals and review reasons. Names appear only when the staff account also has `people.read`. Check **review** records and `cancel_requested` subscriptions against the provider dashboard. **Check subscription status** retrieves Payfast’s current state; paused/locked subscriptions remain visibly distinct from active ones.

**Record reconciliation** records evidence and retries posting a verified gift that previously failed. It cannot invent a payment, charge a card, refund a customer or confirm a late ticket. A paid ticket without a confirmed place requires a completed, fully matched refund before review can close. Outstanding refund matching prevents review closure.

### Refunds

Complete refunds in the provider dashboard. This application does not initiate a financial refund.

- Yoco’s authenticated refund notifications record full or partial refunds automatically, including notifications that arrive before the original payment callback.
- For Payfast, select the correct verified charge, choose **Record completed refund**, enter the exact provider refund ID and amount, and confirm that the refund completed in the provider dashboard.
- If a refund cannot be matched to one charge, its reference and amount appear as awaiting matching. On the correct charge, use **Record completed refund** with that same provider refund ID and amount. Do not include the displayed `provider:mode:` prefix in the refund ID.
- Refund identities are deduplicated. Conflicting references or excess refunds require review rather than a second ledger entry.
- Use Online payments for refunds of online gifts; the manual Giving reversal action cannot bypass the payment ledger. Giving refunds post negative ledger entries on the recorded refund date. An earlier-issued acknowledgement is voided. Any pledge credits against that gift are cancelled for staff review; its remaining, unrefunded amount may be assigned again.
- A full ticket refund cancels its booking and releases the place. A partial ticket refund retains the booking. Cancelling a booking by itself never moves money.

Yoco ledger dates use the authenticated provider transaction date in UTC, so delayed callbacks retain the original date. Payfast ITNs and staff-entered refund confirmations use the UTC verification/recording date. A refund ledger date cannot precede its matching gift. Provider fees and net bank payouts are not donor gifts. Reconcile payout deductions separately against the provider dashboard. Do not combine ticket revenue with charitable giving statements.

## Public giving (no account, no person record)

Anyone can give once at `/give` without signing in. The giver chooses an online-enabled fund, an amount (at least R5.00), a provider and enters an **email address (compulsory)** and an optional name, then pays on the provider's hosted page. Card details never reach this application.

- **No person is created or linked.** The order has no donor; the verified payment posts an **anonymous** `ONLINE` gift (`giving_gifts.donor_id` is null). Anonymous gifts cannot be assigned to pledges and have no donor statement. Public giving is one-off only: monthly giving needs a signed-in, verified donor.
- **Confirmation email.** After the gift is posted, the published `payments.receipt` template is queued automatically to the giver's email (it states it is not a tax certificate; no section 18A certificate is issued). It is revalidated before every send: the charge must still be posted and unrefunded, and the address and content must be exactly as queued. Sandbox payments post no gift and send nothing. If queuing fails it is retried by the next posting attempt (`GUEST_CONFIRMATION_FAILED` appears in the order history).
- **Privacy.** The email is stored encrypted (the payments vault key) and only a keyed hash is used for throttling. The status page is reached with a random access token returned once at checkout and kept in the browser tab's `sessionStorage`; it is sent in a request body, never in a URL.
- **Abuse controls.** A Cloudflare Turnstile check, a hidden honeypot field, and throttles of 10 checkouts per client address and 5 per email per hour. Staff see these orders in **Online payments** as "Public giver"; refunds are recorded exactly as for donors.
- **Setup.** Set `TURNSTILE_SECRET_KEY` (server) and `VITE_TURNSTILE_SITE_KEY` (frontend build). **With no secret, production refuses every public checkout**; other environments skip the check. The production Nginx CSP allows `challenges.cloudflare.com`. The migration `V42__guest_online_giving.sql` must be applied first.

## Operator setup

Run the normal Doctrine migrations before starting this release. Migration `Version20261012060000` adds payment records, callback deduplication and `payments.manage`; existing registration JSON records upgrade through backward-compatible defaults.

Configure private environment values from `.env.production.example`. Never place credentials in frontend code or share them in chat.

| Variable | Purpose |
| --- | --- |
| `PAYMENTS_PUBLIC_URL` | The church application’s public HTTPS base URL, without a trailing application path, query or fragment |
| `PAYFAST_MODE` | `test` or `live` |
| `PAYFAST_MERCHANT_ID`, `PAYFAST_MERCHANT_KEY`, `PAYFAST_PASSPHRASE` | Credentials for the selected Payfast environment; a passphrase is mandatory in this integration |
| `PAYFAST_ITN_IP_RANGES` | Comma-separated trusted Payfast notification networks; shipped defaults follow the published ranges and need review when the provider updates them |
| `YOCO_MODE` | `test` or `live` |
| `YOCO_SECRET_KEY` | Matching Yoco test/live secret key |
| `YOCO_WEBHOOK_SECRET` | The secret returned when registering the Yoco Checkout API webhook |

Payfast hosted checkout sends its required merchant ID/key and signed form fields to the provider. The passphrase, Yoco secret key, webhook secret and subscription tokens remain server-side.

For Payfast, enable recurring billing on the merchant account before accepting monthly setups. Checkout supplies the notification URL `https://YOUR_CHURCH/api/payments/webhooks/payfast`. The server verifies the merchant, source IP, ordered ITN signature and Payfast server validation before processing the payment.

Register one Yoco Checkout API webhook for `https://YOUR_CHURCH/api/payments/webhooks/yoco`, using the secret key in a private server-side/operator tool against the provider’s webhook registration API. Save its returned webhook secret privately. Yoco verification uses the raw request body, webhook ID, signed timestamp (within three minutes), HMAC signature, environment, checkout identity, amount and currency.

The public base URL assumes the frontend and `/api` are served together, as in the production deployment. Preserve the edge proxy’s trusted client-IP forwarding; do not expose the internal PHP/web containers directly. Invalid or unverified callback requests never post money. Provider verification outages return a retryable response. Keep server clocks synchronised.

Production Compose includes the **payments** maintenance worker. It expires reservations and flags cancelled paid tickets for refund review. The development worker uses the same shared workflow lock. If using another deployment system, schedule `php bin/console app:payments:maintain` regularly. `app:operations:check` reports `paymentsNeedingReview` and fails when payment review or cancellation confirmation is outstanding.

Keep `APP_SECRET` stable: verified Payfast subscription tokens are encrypted with a key derived from it. Backups must preserve the corresponding secret securely. Payment histories and refunds are retained; deployment must not discard them when changing provider modes.

## Go-live checks

Use each church-owned provider sandbox/test account to verify checkout, notifications, retries, partial/full refunds, recurring cancellation and late ticket payments. Check provider-side transactions against the application’s charges and ledger. Then configure the matching live credentials and callback registrations. Live activation and bank settlement cannot be verified by local test doubles. No provider accounts or live credentials have been configured by this implementation.

Provider references reviewed for this implementation: [Payfast checkout and ITN documentation](https://developers.payfast.co.za/docs), [Payfast subscriptions API](https://developers.payfast.co.za/api), [Yoco checkout creation](https://developer.yoco.com/api-reference/checkout-api/checkout/create-checkout), [Yoco webhook verification](https://developer.yoco.com/guides/online-payments/webhooks/verifying-the-events), [Yoco recurring-billing limitation](https://support.yoco.help/en/articles/109553-how-to-integrate-yoco-with-your-online-store).
