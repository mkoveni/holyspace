# Online payments milestone

Status: implemented for South Africa/ZAR with Payfast and Yoco. Monthly recurring giving uses Payfast; Yoco supports one-off checkout. See [the operational guide](online-payments.md) for configuration and remaining provider sandbox/live checks.

## Implementation order

1. Hosted, one-off giving through My church, with staff-configured eligible funds and currency. Payment completion posts a gift only after the provider verifies payment. The return page displays pending status until verification completes.
2. Provider-managed recurring giving, with member-owned subscription status and cancellation. Each verified successful charge posts its own gift; failed charges never become gifts. Supported intervals and cancellation behaviour must match the selected provider.
3. Paid event registration with a separate ticket-payment ledger and expiring place reservations. Ticket income must not appear as charitable giving in donor statements. Registration cancellation and payment refund are separate operations with visible status.

## Integration with the current system

- My church already has linked identity, `member.portal`, MFA, and access to the member's own records. Checkout and recurring-giving controls must use that identity rather than accept a caller-supplied donor ID.
- Giving uses integer minor amounts, explicit fund allocations, currency-specific reports and idempotency keys. A successful charge must retain its provider reference and generate exactly one gift, including when callbacks are repeated or arrive out of order.
- Payment orders need their own persistence and audit history. A pending checkout is not a posted gift. Store the amount, currency, selected fund and owner before redirecting to hosted checkout; validate callback amounts and currency against this order.
- Existing registration capacity counts confirmed attendees only and automatically promotes the waitlist. Paid checkout must first introduce expiring reservations into capacity calculations and promotion, including staff booking paths, before collecting ticket payments.
- Webhook processing needs provider verification, durable deduplication and transactional ledger updates. Reconciliation must surface paid-but-unposted payments and late ticket payments after a place reservation expires.
- Network calls must occur outside database transactions. Ambiguous checkout creation or subscription cancellation must remain visible for reconciliation rather than be retried as a fresh payment or reported as successful.
- Provider keys belong in private environment configuration. Local and automated tests use sandbox credentials or test doubles. Production activation requires separately configured provider credentials and webhook/return URLs.

## Acceptance checks

- A member cannot access another member's orders or subscriptions, alter a checkout price, select an ineligible fund, or post a gift through a return URL.
- Invalid callbacks fail verification. Repeated callbacks, concurrent processing and later status notifications do not duplicate gifts or regress a completed payment.
- Amount/currency mismatches and conflicting provider references require staff review without posting money.
- Recurring-giving cancellation, declined charges and callback retries behave according to the provider contract; no browser stores payment credentials.
- Paid booking never exceeds occurrence capacity. Expired or cancelled reservations release places, and a late payment cannot silently confirm a full or cancelled event.
- Ticket transactions remain separate from giving statements; staff can reconcile payment status and refund status independently.
- Backend caller tests, permission-aware staff/member screens and browser journeys cover successful, pending, failed, duplicate and forbidden operations.

## Required provider decision

The church selected South Africa, ZAR, Payfast and Yoco. Their current official documentation was verified before implementation. Credentials and a church-owned provider sandbox/live pilot remain required for activation.
