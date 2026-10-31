package org.onechurch.giving.presentation;
import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_ALLOCATIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_GIFTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_HISTORY;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_REVERSALS;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.GivingIntegrationTestSupport;
import org.springframework.dao.DataIntegrityViolationException;

import java.time.LocalDate;
import java.time.OffsetDateTime;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.UUID;
import java.util.concurrent.CompletableFuture;
class GivingApiIntegrationTest extends GivingIntegrationTestSupport {
    @Test
    void fundCreationRetriesAndVersionedArchivalPreserveHistory() throws Exception {
        Map<String, Object> body =
            Map.of("requestId", UUID.randomUUID(), "name", "General", "description", "Shared needs");
        UUID id = created(request("POST", GIVING + "/funds", admin, body));
        assertThat(created(request("POST", GIVING + "/funds", admin, body))).isEqualTo(id);
        assertThat(
            request("POST", GIVING + "/funds", admin, Map.of("requestId", UUID.randomUUID(), "name", " general "))
                .status())
            .isEqualTo(409);
        Map<String, Object> edit = Map.of("expectedVersion", 0, "name", "General", "description", "Shared needs",
            "active", false, "reason", "Consolidated funds");
        assertThat(request("PUT", GIVING + "/funds/" + id, admin, edit).status()).isEqualTo(204);
        assertThat(request("PUT", GIVING + "/funds/" + id, admin, edit).status()).isEqualTo(409);
        assertThat(request("POST", GIVING + "/gifts", admin, giftBody(id, LocalDate.now(), 100)).status())
            .isEqualTo(409);
        assertThat(request("GET", GIVING + "/funds", admin, null).body()).doesNotContain("General");
        assertThat(request("GET", GIVING + "/funds?archived=true", admin, null).body()).contains("General");
    }
    @Test
    void giftIdempotencyAndReferencesPreventDuplicateMoney() throws Exception {
        UUID fund = fund("General");
        Map<String, Object> body = new HashMap<>(giftBody(fund, LocalDate.now(), 12345));
        body.put("externalReference", "BANK-123");
        UUID id = created(request("POST", GIVING + "/gifts", admin, body));
        assertThat(created(request("POST", GIVING + "/gifts", admin, body))).isEqualTo(id);
        Map<String, Object> duplicate = new HashMap<>(body);
        duplicate.put("requestId", UUID.randomUUID());
        duplicate.put("externalReference", " bank-123 ");
        assertThat(request("POST", GIVING + "/gifts", admin, duplicate).status()).isEqualTo(409);
        body.put("method", "CASH");
        assertThat(request("POST", GIVING + "/gifts", admin, body).status()).isEqualTo(409);
        assertThat(db.fetchCount(GIVING_GIFTS)).isEqualTo(1);
        assertThat(db.fetchCount(GIVING_HISTORY, GIVING_HISTORY.ACTION.eq("GIFT_POSTED"))).isEqualTo(1);
    }
    @Test
    void concurrentIdenticalRequestsPostOnlyOneGift() throws Exception {
        UUID fund = fund("General");
        Map<String, Object> body = giftBody(fund, LocalDate.now(), 10000);
        CompletableFuture<Response> one = CompletableFuture.supplyAsync(() -> postGift(body)),
            two = CompletableFuture.supplyAsync(() -> postGift(body));
        assertThat(created(one.join())).isEqualTo(created(two.join()));
        assertThat(db.fetchCount(GIVING_GIFTS)).isEqualTo(1);
        assertThat(db.fetchCount(GIVING_HISTORY, GIVING_HISTORY.ACTION.eq("GIFT_POSTED"))).isEqualTo(1);
    }
    private Response postGift(Map<String, Object> body) {
        try {
            return request("POST", GIVING + "/gifts", admin, body);
        } catch (Exception e) {
            throw new IllegalStateException(e);
        }
    }
    @Test
    void validatesAllocationBalanceDatesMethodsAndPeople() throws Exception {
        UUID fund = fund("General");
        Map<String, Object> body = new HashMap<>(giftBody(fund, LocalDate.now(), 10000));
        body.put("allocations", List.of(Map.of("fundId", fund, "amountMinor", 9999)));
        assertThat(postGift(body).status()).isEqualTo(400);
        body.put("allocations",
            List.of(Map.of("fundId", fund, "amountMinor", 5000), Map.of("fundId", fund, "amountMinor", 5000)));
        assertThat(postGift(body).status()).isEqualTo(400);
        body.put("allocations", List.of(Map.of("fundId", fund, "amountMinor", 10000)));
        body.put("receivedOn", LocalDate.now().plusDays(2).toString());
        assertThat(postGift(body).status()).isEqualTo(400);
        body.put("receivedOn", LocalDate.now().toString());
        body.put("method", "ONLINE");
        assertThat(postGift(body).status()).isEqualTo(400);
        body.put("method", "BANK");
        body.put("donorId", UUID.randomUUID());
        assertThat(postGift(body).status()).isEqualTo(400);
        body.put("donorId", person("Donor"));
        UUID id = created(postGift(body));
        assertThat(request("GET", GIVING + "/gifts/" + id, admin, null).body()).contains("Donor Mabasa");
        assertThat(db.fetchCount(GIVING_GIFTS)).isEqualTo(1);
    }
    @Test
    void reversalsAreFullImmutableDatedAndSafelyRetried() throws Exception {
        UUID fund = fund("General"), gift = gift(fund, LocalDate.of(2026, 1, 1), 10000);
        Map<String, Object> body =
            Map.of("requestId", UUID.randomUUID(), "reversedOn", "2026-02-01", "reason", "Duplicate bank entry");
        UUID reversed = created(request("POST", GIVING + "/gifts/" + gift + "/reversals", admin, body));
        assertThat(created(request("POST", GIVING + "/gifts/" + gift + "/reversals", admin, body))).isEqualTo(reversed);
        assertThat(request("POST", GIVING + "/gifts/" + gift + "/reversals", admin,
            Map.of("requestId", UUID.randomUUID(), "reversedOn", "2026-02-02", "reason", "Again")).status())
            .isEqualTo(409);
        assertThat(request("GET", GIVING + "/gifts/" + gift, admin, null).body()).contains("Duplicate bank entry",
            "GIFT_REVERSED", "10000");
        assertThat(db.fetchCount(GIVING_GIFTS)).isEqualTo(1);
        assertThat(db.fetchCount(GIVING_REVERSALS)).isEqualTo(1);
        assertThat(request("GET", GIVING + "/totals?from=2026-02-01&to=2026-02-28", admin, null).body())
            .contains("\"netMinor\":\"-10000\"", "\"grossMinor\":\"0\"");
        assertThat(request("GET", GIVING + "/totals?from=2026-01-01&to=2026-02-28", admin, null).body())
            .contains("\"netMinor\":\"0\"", "\"grossMinor\":\"10000\"", "\"reversedMinor\":\"10000\"");
    }
    @Test
    void fundTotalsIncludeOnlyTheirAllocationsAndSeparateCurrencies() throws Exception {
        UUID general = fund("General"), missions = fund("Missions");
        Map<String, Object> body = new HashMap<>(giftBody(general, LocalDate.of(2026, 1, 5), 10000));
        body.put("allocations",
            List.of(Map.of("fundId", general, "amountMinor", 7000), Map.of("fundId", missions, "amountMinor", 3000)));
        UUID id = created(postGift(body));
        body = new HashMap<>(giftBody(general, LocalDate.of(2026, 1, 5), 2000));
        body.put("currency", "USD");
        created(postGift(body));
        assertThat(
            request("GET", GIVING + "/totals?from=2026-01-01&to=2026-01-31&fundId=" + missions, admin, null).body())
            .contains("3000", "ZAR")
            .doesNotContain("USD");
        assertThat(request("GET", GIVING + "/totals?from=2026-01-01&to=2026-01-31", admin, null).body()).contains("USD",
            "ZAR", "10000", "2000");
        assertThat(
            request("GET", GIVING + "/gifts?from=2026-01-01&to=2026-01-31&fundId=" + missions, admin, null).body())
            .contains(id.toString(), "10000");
    }
    @Test
    void databaseRejectsUnbalancedAndMutablePostedEvidence() throws Exception {
        UUID fund = fund("General"), gift = gift(fund, LocalDate.now(), 10000);
        assertThatThrownBy(() -> db.update(GIVING_GIFTS)
            .set(GIVING_GIFTS.AMOUNT_MINOR, 20000L)
            .where(GIVING_GIFTS.ID.eq(gift))
            .execute()).isInstanceOf(DataIntegrityViolationException.class);
        assertThatThrownBy(() -> db.deleteFrom(GIVING_ALLOCATIONS).where(GIVING_ALLOCATIONS.GIFT_ID.eq(gift)).execute())
            .isInstanceOf(DataIntegrityViolationException.class);
        assertThatThrownBy(() -> db.insertInto(GIVING_GIFTS)
            .set(GIVING_GIFTS.ID, UUID.randomUUID())
            .set(GIVING_GIFTS.AMOUNT_MINOR, 100L)
            .set(GIVING_GIFTS.CURRENCY, "ZAR")
            .set(GIVING_GIFTS.RECEIVED_ON, LocalDate.now())
            .set(GIVING_GIFTS.METHOD, "BANK")
            .set(GIVING_GIFTS.REQUEST_HASH, "a".repeat(64))
            .set(GIVING_GIFTS.CREATED_BY, adminId)
            .set(GIVING_GIFTS.CREATED_AT, OffsetDateTime.now())
            .execute()).isInstanceOf(DataIntegrityViolationException.class);
        assertThat(db.fetchCount(GIVING_GIFTS)).isEqualTo(1);
    }
    @Test
    void financialReadAndReversalPermissionsRemainIndependent() throws Exception {
        UUID fund = fund("General"), gift = gift(fund, LocalDate.now(), 10000);
        revoke("giving.manage");
        assertThat(request("GET", GIVING + "/gifts" + period(LocalDate.now(), LocalDate.now()), admin, null).status())
            .isEqualTo(200);
        assertThat(request("POST", GIVING + "/gifts", admin, giftBody(fund, LocalDate.now(), 100)).status())
            .isEqualTo(403);
        revoke("giving.reverse");
        assertThat(request("POST", GIVING + "/gifts/" + gift + "/reversals", admin,
            Map.of("requestId", UUID.randomUUID(), "reversedOn", LocalDate.now().toString(), "reason", "Correction"))
            .status()).isEqualTo(403);
        revoke("giving.read");
        assertThat(request("GET", GIVING + "/gifts/" + gift, admin, null).status()).isEqualTo(403);
        assertThat(request("GET", GIVING + "/funds", null, null).status()).isEqualTo(401);
    }
    @Test
    void aggregateNamespacesKeepFundHistoryOutOfGiftEvidence() throws Exception {
        UUID fund = fund("General");
        Map<String, Object> body = new HashMap<>(giftBody(fund, LocalDate.now(), 100));
        body.put("requestId", fund);
        UUID gift = created(postGift(body));
        assertThat(request("GET", GIVING + "/gifts/" + gift, admin, null).body()).contains("GIFT_POSTED")
            .doesNotContain("FUND_CREATED");
    }
    @Test
    void fractionalMinorUnitsAreRejectedRatherThanRounded() throws Exception {
        UUID fund = fund("General");
        Map<String, Object> body = new HashMap<>(giftBody(fund, LocalDate.now(), 100));
        body.put("amountMinor", new java.math.BigDecimal("100.9"));
        body.put("allocations", List.of(Map.of("fundId", fund, "amountMinor", new java.math.BigDecimal("100.9"))));
        assertThat(postGift(body).status()).isEqualTo(400);
        body.put("amountMinor", 100);
        assertThat(postGift(body).status()).isEqualTo(400);
        body.put("allocations", java.util.Arrays.asList((Object) null));
        assertThat(postGift(body).status()).isEqualTo(400);
        assertThat(db.fetchCount(GIVING_GIFTS)).isZero();
    }
    @Test
    void concurrentReversalsCannotCorrectTheSameMoneyTwice() throws Exception {
        UUID gift = gift(fund("General"), LocalDate.now(), 100);
        java.util.function.Supplier<Response> reversal = () -> {
            try {
                return request("POST", GIVING + "/gifts/" + gift + "/reversals", admin, Map.of("requestId",
                    UUID.randomUUID(), "reversedOn", LocalDate.now().toString(), "reason", "Duplicate entry"));
            } catch (Exception e) {
                throw new IllegalStateException(e);
            }
        };
        CompletableFuture<Response> one = CompletableFuture.supplyAsync(reversal),
            two = CompletableFuture.supplyAsync(reversal);
        assertThat(List.of(one.join().status(), two.join().status())).containsExactlyInAnyOrder(201, 409);
        assertThat(db.fetchCount(GIVING_REVERSALS)).isEqualTo(1);
    }
    @Test
    void pagingFiltersAndPublicDocumentationAreBounded() throws Exception {
        UUID fund = fund("General");
        gift(fund, LocalDate.now(), 100);
        gift(fund, LocalDate.now(), 200);
        assertThat(
            request("GET", GIVING + "/gifts" + period(LocalDate.now(), LocalDate.now()) + "&limit=1", admin, null)
                .data()
                .get("total"))
            .isEqualTo(2);
        assertThat(request("GET", GIVING + "/gifts?from=2026-02-01&to=2026-01-01", admin, null).status())
            .isEqualTo(400);
        assertThat(request("GET", GIVING + "/funds?limit=101", admin, null).status()).isEqualTo(400);
        assertThat(request("GET", "/v3/api-docs", null, null).body()).contains("/api/giving/gifts/{giftId}/reversals",
            "netMinor", "giving.reverse");
    }
}
