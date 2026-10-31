# Giving statements and reports

Open **Giving → Statements and reports**. Access requires `giving.manage`; finding a statement donor also needs `people.read` for backend autocomplete.

## Generate a statement or report

1. Choose the start and end dates, inclusive. A report covers up to 367 calendar days, including an annual leap-year period.
2. Select a donor for a personal statement, or leave the donor blank for the church report. Optionally select a fund.
3. Choose **Generate report**. Review currency totals, fund totals and the dated ledger.
4. Choose **Print statement/report** for a print-friendly document or **Download CSV** for fund/ledger export. Downloads use the generated report's filters, even if you have changed the controls without generating again.

Currencies are never added together. Gifts count on the received date, even if they were subsequently reversed. Reversals count as negative entries on the reversal date. Consequently, a period may show negative net giving. A statement with no entries displays that explicitly. Anonymous giving appears in church/fund reports, not in a named donor statement.

Amounts are stored as integer minor units. CSV includes signed minor-unit and ordinary decimal amounts, respecting currency fraction digits (including zero- and three-decimal currencies). User-entered donor and fund names are escaped against spreadsheet formula execution. The CSV is an operational ledger export, not a provider refund instruction or a jurisdiction-specific accounting format.

Statements are live views of currently recorded gift/reversal entries and current donor/fund names. They are not issued immutable receipts or archived historical snapshots. Regenerate after corrections, and review the report before sharing it. No document is emailed automatically. The printed statement is a donation acknowledgement and makes no claim of tax deductibility.

## Track pledge progress explicitly

1. Select **Progress through** and choose **Refresh progress**.
2. Review each pledge's promised, applied and remaining amounts, due date, recorded status and overdue flag.
3. Choose **Pledge to credit**, then a posted gift from the same donor/currency. Enter an ordinary currency amount and choose **Apply gift to pledge**.
4. The backend checks the pledge's fund, remaining pledged amount and the unused amount of the gift's allocation to that fund. One gift may contribute to several pledges only within its available allocation. Unassigned gifts do not count automatically.
5. To correct a mistaken assignment, choose **Cancel assignment**, provide a reason and confirm. The assignment remains in history and its amount becomes available again. Make a new assignment deliberately if needed.

Only open pledges accept new assignments. Gift reversals remove their contribution from progress on and after the reversal date. Progress uses currently active assignments and current pledge status, combined with gift/reversal dates through the selected date; it is not a historical audit reconstruction. Cancelling an assignment affects all such live views. Automatic changes to fulfilled/cancelled pledge status are not made; staff retain those existing actions in Giving.

Assignment requests carry a unique reference for safe retries. Reusing it with different details is a conflict. Cancellation requires the current record version and a non-empty reason. Assignments and cancellations use the shared workflow lock, transaction and audit journal. They never change the gift's monetary entries or reconciliation batch.

## API and deployment

All endpoints require authentication and `giving.manage`:

- `GET /api/giving/reports?from=YYYY-MM-DD&to=YYYY-MM-DD&donorId=UUID&fundId=UUID` — optional donor/fund, with ledger, currency totals and fund totals.
- `GET /api/giving/reports/export` — same filters, UTF-8 CSV download.
- `GET /api/giving/pledge-progress?asOf=YYYY-MM-DD` — current pledge/assignment projection with date-based gift and reversal contributions.
- `POST /api/giving/pledge-allocations` — `{pledgeId, giftId, amountMinor, reference}`.
- `POST /api/giving/pledge-allocations/{id}/cancel` — `{expectedVersion, reason}`.

Report and CSV responses use `Cache-Control: no-store`. Assignment records use the existing Giving document table under `pledge_allocations`; no schema migration or new permissions are required. Deploy the updated backend/frontend together so the new record type and screens are available.

These reports currently use the existing Giving document collections and load the selected ledger for display/export. Indexed reporting projections, larger-data pagination, scheduled/bulk statement delivery, archived statement issuance, payment-provider reporting and automatic pledge matching remain extensions. The church should validate performance with its expected history during the staff pilot.

## Online gifts and tickets

Verified live online gifts flow into this same ledger and statements. Sandbox charges and paid event tickets remain separate. Refunds can be partial; refunded gift acknowledgements are voided and pledge credits are cancelled for review. See [the online payments guide](online-payments.md).
