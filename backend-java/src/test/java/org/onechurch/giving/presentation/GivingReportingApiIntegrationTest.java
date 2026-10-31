package org.onechurch.giving.presentation;
import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_ACKNOWLEDGEMENTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_PLEDGE_ASSIGNMENTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_RECONCILIATION_ITEMS;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_RECONCILIATION_REVIEWS;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.GivingIntegrationTestSupport;

import java.time.LocalDate;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.UUID;
import java.util.concurrent.CompletableFuture;
class GivingReportingApiIntegrationTest extends GivingIntegrationTestSupport {
    private UUID pledge(UUID donor, UUID fund, long amount) throws Exception {
        return created(request("POST", GIVING + "/pledges", admin,
            Map.of("requestId", UUID.randomUUID(), "donorId", donor, "fundId", fund, "amountMinor", amount, "currency",
                "ZAR", "startsOn", "2026-01-01", "endsOn", "2026-12-31")));
    }
    private UUID namedGift(UUID donor, UUID fund, long amount, String date) throws Exception {
        Map<String, Object> body = new HashMap<>(giftBody(fund, LocalDate.parse(date), amount));
        body.put("donorId", donor);
        return created(request("POST", GIVING + "/gifts", admin, body));
    }
    private Response assign(UUID pledge, UUID gift, long amount, long version) throws Exception {
        return request("POST", GIVING + "/pledges/" + pledge + "/assignments", admin,
            Map.of("requestId", UUID.randomUUID(), "giftId", gift, "amountMinor", amount, "expectedVersion", version));
    }
    private Map<?, ?> progress(UUID pledge) throws Exception {
        return (Map<?, ?>) request("GET", GIVING + "/pledges/" + pledge, admin, null).data().get("summary");
    }
    private void reverse(UUID gift, String date) throws Exception {
        created(request("POST", GIVING + "/gifts/" + gift + "/reversals", admin,
            Map.of("requestId", UUID.randomUUID(), "reversedOn", date, "reason", "Duplicate ledger entry")));
    }
    private Map<String, Object> batch(List<UUID> gifts, long expected, String reference) {
        return Map.of("requestId", UUID.randomUUID(), "giftIds", gifts, "expectedMinor", expected, "currency", "ZAR",
            "reconciledOn", "2026-02-01", "reference", reference, "reason", "Verified bank evidence");
    }
    @Test
    void pledgesRequireExplicitAssignmentsAndReversalsReduceProgress() throws Exception {
        UUID fund = fund("General"), donor = person("Donor"), pledge = pledge(donor, fund, 10000),
            gift = namedGift(donor, fund, 10000, "2026-01-05");
        assertThat(((Number) progress(pledge).get("creditedMinor")).longValue()).isZero();
        created(assign(pledge, gift, 10000, 0));
        assertThat(progress(pledge).get("fulfilled")).isEqualTo(true);
        reverse(gift, "2026-02-01");
        assertThat(((Number) progress(pledge).get("creditedMinor")).longValue()).isZero();
        assertThat(progress(pledge).get("fulfilled")).isEqualTo(false);
        assertThat(request("GET", GIVING + "/pledges/" + pledge, admin, null).body()).contains("giftReversed\":true");
    }
    @Test
    void allocationsCannotBeDoubleSpentAndCancellationPreservesEvidence() throws Exception {
        UUID fund = fund("General"), donor = person("Donor"), first = pledge(donor, fund, 10000),
            second = pledge(donor, fund, 10000), gift = namedGift(donor, fund, 10000, "2026-01-05");
        UUID link = created(assign(first, gift, 6000, 0));
        assertThat(assign(second, gift, 5000, 0).status()).isEqualTo(409);
        assertThat(request("POST", GIVING + "/pledge-assignments/" + link + "/cancel", admin,
            Map.of("expectedVersion", 0, "reason", "Assigned to wrong commitment")).status()).isEqualTo(204);
        created(assign(second, gift, 10000, 0));
        assertThat(db.fetchCount(GIVING_PLEDGE_ASSIGNMENTS)).isEqualTo(2);
        assertThat(request("POST", GIVING + "/pledges/" + first + "/cancel", admin,
            Map.of("expectedVersion", 2, "reason", "Commitment withdrawn")).status()).isEqualTo(204);
        assertThat(assign(first, gift, 100, 3).status()).isEqualTo(409);
    }
    @Test
    void assignmentsCheckDonorFundCurrencyDatesVersionsAndExactCents() throws Exception {
        UUID fund = fund("General"), donor = person("Donor"), pledge = pledge(donor, fund, 10000),
            other = namedGift(person("Other"), fund, 10000, "2026-01-05");
        assertThat(assign(pledge, other, 100, 0).status()).isEqualTo(400);
        UUID wrongFund = namedGift(donor, fund("Missions"), 10000, "2026-01-05");
        assertThat(assign(pledge, wrongFund, 100, 0).status()).isEqualTo(409);
        UUID gift = namedGift(donor, fund, 10000, "2026-01-05");
        assertThat(assign(pledge, gift, 100, 1).status()).isEqualTo(409);
        assertThat(request("POST", GIVING + "/pledges/" + pledge + "/assignments", admin,
            Map.of("requestId", UUID.randomUUID(), "giftId", gift, "expectedVersion", 0, "amountMinor", 1.9)).status())
            .isEqualTo(400);
        assertThat(request("GET", GIVING + "/pledges/" + pledge + "/candidates", admin, null).body())
            .contains(gift.toString())
            .doesNotContain(other.toString(), wrongFund.toString());
    }
    @Test
    void concurrentAssignmentsCannotExceedTheGiftFundBudget() throws Exception {
        UUID fund = fund("General"), donor = person("Donor"), a = pledge(donor, fund, 10000),
            b = pledge(donor, fund, 10000), gift = namedGift(donor, fund, 10000, "2026-01-05");
        CompletableFuture<Response> one = CompletableFuture.supplyAsync(() -> assignAsync(a, gift)),
            two = CompletableFuture.supplyAsync(() -> assignAsync(b, gift));
        assertThat(List.of(one.join().status(), two.join().status())).containsExactlyInAnyOrder(201, 409);
        assertThat(db.fetchCount(GIVING_PLEDGE_ASSIGNMENTS)).isEqualTo(1);
    }
    private Response assignAsync(UUID pledge, UUID gift) {
        try {
            return assign(pledge, gift, 10000, 0);
        } catch (Exception e) {
            throw new IllegalStateException(e);
        }
    }
    @Test
    void statementIncludesPeriodReversalsOfEarlierGiftsWithoutMixingCurrencies() throws Exception {
        UUID fund = fund("General"), donor = person("Donor"), gift = namedGift(donor, fund, 10000, "2026-01-05");
        reverse(gift, "2026-02-01");
        Map<String, Object> usd = new HashMap<>(giftBody(fund, LocalDate.of(2026, 2, 2), 2000));
        usd.put("donorId", donor);
        usd.put("currency", "USD");
        created(request("POST", GIVING + "/gifts", admin, usd));
        Response statement =
            request("GET", GIVING + "/statements/" + donor + "?from=2026-02-01&to=2026-02-28", admin, null);
        assertThat(statement.status()).isEqualTo(200);
        assertThat(statement.body()).contains("Donor Mabasa", "REVERSAL", "-10000", "USD", "2000", "generatedAt");
        io.restassured.RestAssured.given()
            .baseUri("http://localhost")
            .port(port)
            .header("Authorization", "Bearer " + admin)
            .get(GIVING + "/ledger.csv?from=2026-02-01&to=2026-02-28&fundId=" + fund)
            .then()
            .statusCode(200)
            .contentType("text/csv");
    }
    @Test
    void acknowledgementNumbersAreUniqueSnapshotsAndBecomeVoidOnReversal() throws Exception {
        UUID fund = fund("General"), donor = person("Donor"), gift = namedGift(donor, fund, 10000, "2026-01-05");
        Map<String, Object> body = Map.of("requestId", UUID.randomUUID());
        UUID ack = created(request("POST", GIVING + "/gifts/" + gift + "/acknowledgements", admin, body));
        assertThat(created(request("POST", GIVING + "/gifts/" + gift + "/acknowledgements", admin, body)))
            .isEqualTo(ack);
        String number = (String) request("GET", GIVING + "/acknowledgements/" + ack, admin, null).data().get("number");
        assertThat(number).startsWith("G-");
        assertThat(request("PUT", GIVING + "/funds/" + fund, admin, Map.of("expectedVersion", 0, "name", "Renamed",
            "description", "", "active", true, "reason", "Updated display name")).status()).isEqualTo(204);
        assertThat(request("GET", GIVING + "/acknowledgements/" + ack, admin, null).body()).contains("General")
            .doesNotContain("Renamed");
        reverse(gift, "2026-02-01");
        Response voided = request("GET", GIVING + "/acknowledgements/" + ack, admin, null);
        assertThat(voided.data().get("voided")).isEqualTo(true);
        assertThat(voided.data().get("number")).isEqualTo(number);
        assertThat(request("POST", GIVING + "/gifts/" + gift + "/acknowledgements", admin,
            Map.of("requestId", UUID.randomUUID())).status()).isEqualTo(409);
        assertThat(db.fetchCount(GIVING_ACKNOWLEDGEMENTS)).isEqualTo(1);
    }
    @Test
    void reconciliationPreservesSnapshotsAndLaterReversalsReopenReview() throws Exception {
        UUID fund = fund("General"), gift = gift(fund, LocalDate.of(2026, 1, 5), 10000);
        Map<String, Object> body = batch(List.of(gift), 10000, "CASH-001");
        UUID batch = created(request("POST", GIVING + "/reconciliations", admin, body));
        assertThat(created(request("POST", GIVING + "/reconciliations", admin, body))).isEqualTo(batch);
        assertThat(((Map<?, ?>) request("GET", GIVING + "/reconciliations/" + batch, admin, null).data().get("summary"))
            .get("needsReview")).isEqualTo(false);
        reverse(gift, "2026-02-01");
        Map<?, ?> summary =
            (Map<?, ?>) request("GET", GIVING + "/reconciliations/" + batch, admin, null).data().get("summary");
        assertThat(summary.get("needsReview")).isEqualTo(true);
        Map<String, Object> review = Map.of("requestId", UUID.randomUUID(), "expectedVersion", 0, "outcome",
            "EXPLAINED", "note", "Bank correction verified");
        UUID reviewed = created(request("POST", GIVING + "/reconciliations/" + batch + "/reviews", admin, review));
        assertThat(created(request("POST", GIVING + "/reconciliations/" + batch + "/reviews", admin, review)))
            .isEqualTo(reviewed);
        assertThat(((Map<?, ?>) request("GET", GIVING + "/reconciliations/" + batch, admin, null).data().get("summary"))
            .get("needsReview")).isEqualTo(false);
        assertThat(db.fetchCount(GIVING_RECONCILIATION_REVIEWS)).isEqualTo(1);
    }
    @Test
    void mismatchesRequireEvidenceAndGiftBatchMembershipIsUnique() throws Exception {
        UUID gift = gift(fund("General"), LocalDate.of(2026, 1, 5), 10000);
        Map<String, Object> body = new HashMap<>(batch(List.of(gift), 11000, "BANK-001"));
        body.put("reason", "");
        assertThat(request("POST", GIVING + "/reconciliations", admin, body).status()).isEqualTo(400);
        body.put("reason", "Recorded bank charge pending review");
        UUID batch = created(request("POST", GIVING + "/reconciliations", admin, body));
        assertThat(((Map<?, ?>) request("GET", GIVING + "/reconciliations/" + batch, admin, null).data().get("summary"))
            .get("needsReview")).isEqualTo(true);
        assertThat(
            request("POST", GIVING + "/reconciliations", admin, batch(List.of(gift), 10000, "BANK-002")).status())
            .isEqualTo(409);
        assertThat(db.fetchCount(GIVING_RECONCILIATION_ITEMS)).isEqualTo(1);
    }
    @Test
    void scopedIssuanceReconciliationAndExportRequireTheirOwnPermissions() throws Exception {
        UUID gift = gift(fund("General"), LocalDate.of(2026, 1, 5), 10000);
        revoke("giving.acknowledge");
        revoke("giving.reconcile");
        revoke("giving.export");
        assertThat(request("POST", GIVING + "/gifts/" + gift + "/acknowledgements", admin,
            Map.of("requestId", UUID.randomUUID())).status()).isEqualTo(403);
        assertThat(
            request("POST", GIVING + "/reconciliations", admin, batch(List.of(gift), 10000, "BANK-001")).status())
            .isEqualTo(403);
        assertThat(request("GET", GIVING + "/ledger.csv?from=2026-01-01&to=2026-01-31", admin, null).status())
            .isEqualTo(403);
        assertThat(request("GET", GIVING + "/pledges", admin, null).status()).isEqualTo(200);
    }
    @Test
    void financialReadsNeverIssueAcknowledgementsOrCreateReconciliationEvidence() throws Exception {
        UUID gift = gift(fund("General"), LocalDate.of(2026, 1, 5), 10000);
        assertThat(request("GET", GIVING + "/gifts/" + gift + "/acknowledgement", admin, null).status()).isEqualTo(200);
        assertThat(request("GET", GIVING + "/reconciliations", admin, null).status()).isEqualTo(200);
        assertThat(db.fetchCount(GIVING_ACKNOWLEDGEMENTS)).isZero();
        assertThat(db.fetchCount(GIVING_RECONCILIATION_ITEMS)).isZero();
    }
    @Test
    void assignmentRetriesStayIdempotentAfterThePledgeVersionChanges() throws Exception {
        UUID fund = fund("General"), donor = person("Donor"), pledge = pledge(donor, fund, 10000),
            gift = namedGift(donor, fund, 10000, "2026-01-05");
        Map<String, Object> body =
            Map.of("requestId", UUID.randomUUID(), "giftId", gift, "amountMinor", 10000, "expectedVersion", 0);
        UUID assigned = created(request("POST", GIVING + "/pledges/" + pledge + "/assignments", admin, body));
        assertThat(created(request("POST", GIVING + "/pledges/" + pledge + "/assignments", admin, body)))
            .isEqualTo(assigned);
        assertThat(db.fetchCount(GIVING_PLEDGE_ASSIGNMENTS)).isEqualTo(1);
    }
    @Test
    void concurrentBatchCreationCannotReconcileOneGiftTwice() throws Exception {
        UUID gift = gift(fund("General"), LocalDate.of(2026, 1, 5), 10000);
        CompletableFuture<Response> one = CompletableFuture.supplyAsync(() -> batchAsync(gift, "BANK-A")),
            two = CompletableFuture.supplyAsync(() -> batchAsync(gift, "BANK-B"));
        assertThat(List.of(one.join().status(), two.join().status())).containsExactlyInAnyOrder(201, 409);
    }
    private Response batchAsync(UUID gift, String reference) {
        try {
            return request("POST", GIVING + "/reconciliations", admin, batch(List.of(gift), 10000, reference));
        } catch (Exception e) {
            throw new IllegalStateException(e);
        }
    }
    @Test
    void laterSourceReversalsInvalidateExplainedReconciliationEvidence() throws Exception {
        UUID fund = fund("General"), first = gift(fund, LocalDate.of(2026, 1, 5), 10000),
            second = gift(fund, LocalDate.of(2026, 1, 6), 10000), batch = created(
                request("POST", GIVING + "/reconciliations", admin, batch(List.of(first, second), 20000, "BANK-REV")));
        created(request("POST", GIVING + "/reconciliations/" + batch + "/reviews", admin, Map.of("requestId",
            UUID.randomUUID(), "expectedVersion", 0, "outcome", "EXPLAINED", "note", "Original totals verified")));
        reverse(second, "2026-02-01");
        assertThat(((Map<?, ?>) request("GET", GIVING + "/reconciliations/" + batch, admin, null).data().get("summary"))
            .get("needsReview")).isEqualTo(true);
        assertThat(request("POST", GIVING + "/reconciliations/" + batch + "/reviews", admin, Map.of("requestId",
            UUID.randomUUID(), "expectedVersion", 0, "outcome", "EXPLAINED", "note", "Stale review screen")).status())
            .isEqualTo(409);
    }

}
