package org.onechurch.payments.presentation;
import static org.assertj.core.api.Assertions.assertThat;
import static org.mockito.ArgumentMatchers.any;
import static org.mockito.ArgumentMatchers.anyBoolean;
import static org.mockito.ArgumentMatchers.anyString;
import static org.mockito.Mockito.when;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_GIFTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_PAYMENT_REFUNDS;
import static org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_CHARGES;
import static org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_NOTIFICATIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_ORDERS;
import static org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_REFUNDS;

import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.onechurch.payments.application.contract.PaymentGateway;
import org.onechurch.payments.application.contract.SubscriptionVault;
import org.onechurch.shared.infrastructure.PaymentIntegrationTestSupport;
import org.springframework.test.context.bean.override.mockito.MockitoBean;
import org.springframework.transaction.support.TransactionSynchronizationManager;

import java.time.LocalDate;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.UUID;
import java.util.concurrent.CompletableFuture;
class PaymentsApiIntegrationTest extends PaymentIntegrationTestSupport {
    @MockitoBean PaymentGateway gateway;
    @MockitoBean SubscriptionVault vault;
    @BeforeEach
    void provider() {
        when(gateway.mode(anyString())).thenReturn("live");
        when(gateway.providers()).thenReturn(List.of(new PaymentGateway.Provider("yoco", "Yoco", true, "live", false),
            new PaymentGateway.Provider("payfast", "Payfast", true, "live", true)));
        when(gateway.checkout(any(), anyString(), anyString())).thenAnswer(call -> {
            assertThat(TransactionSynchronizationManager.isActualTransactionActive()).isFalse();
            org.onechurch.payments.domain.PaymentOrder o = call.getArgument(0);
            return new PaymentGateway.Checkout("co_" + o.id(), "https://c.yoco.com/checkout/one", Map.of());
        });
        when(vault.encrypt(anyString())).thenReturn("encrypted-secret");
        when(vault.decrypt(anyString())).thenReturn("12345678-1234-1234-1234-123456789012");
        when(gateway.subscription(any(), anyString(), anyBoolean())).thenReturn("CANCELLED");
    }
    private UUID order(String provider, boolean recurring) throws Exception {
        return created(
            request("POST", "/api/payments", admin, Map.of("requestId", UUID.randomUUID(), "donorId", person("Donor"),
                "fundId", fund("General"), "amountMinor", 10000, "provider", provider, "recurring", recurring)));
    }
    private void ready(UUID order) throws Exception {
        assertThat(request("POST", "/api/payments/" + order + "/checkout", admin, null).status()).isEqualTo(200);
    }
    private Response event(UUID order, String type, String reference, long amount, String token) throws Exception {
        when(gateway.verify(anyString(), anyString(), any(), anyString()))
            .thenReturn(new PaymentGateway.Notification("payfast", "live", reference + ":" + type, type, reference,
                null, order, amount, "ZAR", token, LocalDate.now()));
        return request("POST", "/api/payments/webhooks/payfast", null, Map.of("event", reference));
    }
    private UUID charge(UUID order) {
        return db.select(PAYMENTS_CHARGES.ID)
            .from(PAYMENTS_CHARGES)
            .where(PAYMENTS_CHARGES.ORDER_ID.eq(order))
            .fetchOne(PAYMENTS_CHARGES.ID);
    }
    @Test
    void checkoutIsDurableAndRetriesDoNotPostMoney() throws Exception {
        UUID donor = person("Donor"), fund = fund("General"), id = UUID.randomUUID();
        Map<String, Object> body =
            Map.of("requestId", id, "donorId", donor, "fundId", fund, "amountMinor", 10000, "provider", "yoco");
        assertThat(created(request("POST", "/api/payments", admin, body))).isEqualTo(id);
        assertThat(created(request("POST", "/api/payments", admin, body))).isEqualTo(id);
        Map<String, Object> changed = new HashMap<>(body);
        changed.put("amountMinor", 12000);
        assertThat(request("POST", "/api/payments", admin, changed).status()).isEqualTo(409);
        ready(id);
        ready(id);
        assertThat(db.fetchCount(PAYMENTS_ORDERS)).isEqualTo(1);
        assertThat(db.fetchCount(GIVING_GIFTS)).isZero();
    }
    @Test
    void verifiedLiveChargePostsExactlyOneGiftAndReturnQueryCannotAssertPayment() throws Exception {
        UUID id = order("payfast", false);
        ready(id);
        assertThat(request("GET", "/api/payments/" + id + "?status=paid", admin, null).body()).contains("READY")
            .doesNotContain("ONLINE");
        assertThat(event(id, "PAID", "charge_1", 10000, null).status()).isEqualTo(204);
        assertThat(event(id, "PAID", "charge_1", 10000, null).status()).isEqualTo(204);
        assertThat(db.fetchCount(GIVING_GIFTS)).isEqualTo(1);
        assertThat(db.fetchCount(PAYMENTS_CHARGES)).isEqualTo(1);
        assertThat(db.fetchCount(PAYMENTS_NOTIFICATIONS)).isEqualTo(1);
        UUID gift = db.select(GIVING_GIFTS.ID).from(GIVING_GIFTS).fetchOne(GIVING_GIFTS.ID);
        assertThat(request("POST", "/api/giving/gifts/" + gift + "/reversals", admin, Map.of("requestId",
            UUID.randomUUID(), "reversedOn", LocalDate.now().toString(), "reason", "Cannot bypass Payments")).status())
            .isEqualTo(409);
    }
    @Test
    void wrongAmountsAndConflictingNotificationsNeverPost() throws Exception {
        UUID id = order("payfast", false);
        assertThat(event(id, "PAID", "wrong", 9000, null).status()).isEqualTo(204);
        assertThat(event(id, "PAID", "wrong", 10000, null).status()).isEqualTo(204);
        assertThat(db.fetchCount(GIVING_GIFTS)).isZero();
        assertThat(request("GET", "/api/payments/" + id, admin, null).body()).contains("conflicting");
    }
    @Test
    void sandboxChargesNeverReachGiving() throws Exception {
        when(gateway.mode(anyString())).thenReturn("test");
        UUID id = order("payfast", false);
        when(gateway.verify(anyString(), anyString(), any(), anyString())).thenReturn(new PaymentGateway.Notification(
            "payfast", "test", "sandbox", "PAID", "sandbox", null, id, 10000, "ZAR", null, LocalDate.now()));
        assertThat(request("POST", "/api/payments/webhooks/payfast", null, Map.of()).status()).isEqualTo(204);
        assertThat(db.fetchCount(PAYMENTS_CHARGES)).isEqualTo(1);
        assertThat(db.fetchCount(GIVING_GIFTS)).isZero();
    }
    @Test
    void partialRefundsAreDatedIdempotentAndCannotExceedCharge() throws Exception {
        UUID id = order("payfast", false);
        event(id, "PAID", "charged", 10000, null);
        UUID charge = charge(id);
        Map<String, Object> body = Map.of("chargeId", charge, "providerReference", "refund_1", "amountMinor", 2500,
            "refundedOn", LocalDate.now().toString(), "reason", "Completed in provider dashboard");
        assertThat(request("POST", "/api/payments/" + id + "/refunds", admin, body).status()).isEqualTo(204);
        assertThat(request("POST", "/api/payments/" + id + "/refunds", admin, body).status()).isEqualTo(204);
        assertThat(db.fetchCount(PAYMENTS_REFUNDS)).isEqualTo(1);
        assertThat(db.fetchCount(GIVING_PAYMENT_REFUNDS)).isEqualTo(1);
        assertThat(
            request("GET", "/api/giving/totals?from=" + LocalDate.now() + "&to=" + LocalDate.now(), admin, null).body())
            .contains("\"netMinor\":\"7500\"");
        Map<String, Object> excess = new HashMap<>(body);
        excess.put("providerReference", "refund_2");
        excess.put("amountMinor", 8000);
        assertThat(request("POST", "/api/payments/" + id + "/refunds", admin, excess).status()).isEqualTo(409);
    }
    @Test
    void refundsBeforeChargesAreRetainedAndAppliedWhenMatched() throws Exception {
        UUID id = order("payfast", false);
        event(id, "REFUNDED", "refund_late", 2000, null);
        assertThat(db.fetchCount(GIVING_PAYMENT_REFUNDS)).isZero();
        event(id, "PAID", "charge_late", 10000, null);
        assertThat(db.fetchCount(GIVING_PAYMENT_REFUNDS)).isEqualTo(1);
        assertThat(request("GET", "/api/payments/" + id, admin, null).body()).contains("\"applied\":true");
    }
    @Test
    void settlementPreservesFeesAndBecomesStaleAfterRefund() throws Exception {
        UUID id = order("payfast", false);
        event(id, "PAID", "charged", 10000, null);
        UUID charge = charge(id), review = UUID.randomUUID();
        Map<String, Object> body =
            Map.of("requestId", review, "chargeId", charge, "expectedVersion", 0, "settlementReference", "BANK-001",
                "feeMinor", 300, "bankMinor", 9700, "note", "Matched bank and provider statement");
        assertThat(created(request("POST", "/api/payments/" + id + "/reviews", admin, body))).isEqualTo(review);
        assertThat(created(request("POST", "/api/payments/" + id + "/reviews", admin, body))).isEqualTo(review);
        assertThat(request("GET", "/api/payments/" + id, admin, null).body()).contains("\"needsReview\":false");
        event(id, "REFUNDED", "refund_after", 1000, null);
        assertThat(request("GET", "/api/payments/" + id, admin, null).body()).contains("\"needsReview\":true",
            "BANK-001");
    }
    @Test
    void recurringChargesRemainSeparateAndCancellationNeedsProviderConfirmation() throws Exception {
        UUID id = order("payfast", true);
        String token = "12345678-1234-1234-1234-123456789012";
        event(id, "PAID", "month_1", 10000, token);
        event(id, "PAID", "month_2", 10000, token);
        assertThat(db.fetchCount(GIVING_GIFTS)).isEqualTo(2);
        long version = db.select(PAYMENTS_ORDERS.VERSION)
            .from(PAYMENTS_ORDERS)
            .where(PAYMENTS_ORDERS.ID.eq(id))
            .fetchOne(PAYMENTS_ORDERS.VERSION);
        assertThat(request("POST", "/api/payments/" + id + "/subscription", admin,
            Map.of("expectedVersion", version, "cancel", true)).status()).isEqualTo(204);
        assertThat(request("GET", "/api/payments/" + id, admin, null).body()).contains("CANCELLED")
            .doesNotContain("encrypted-secret", token);
        event(id, "PAID", "inflight", 10000, token);
        assertThat(db.fetchCount(GIVING_GIFTS)).isEqualTo(3);
        assertThat(request("GET", "/api/payments/" + id, admin, null).body()).contains("CANCELLED");
    }
    @Test
    void revokedWritePermissionsDoNotInheritFromManagement() throws Exception {
        UUID id = order("payfast", false);
        event(id, "PAID", "charged", 10000, null);
        UUID charge = charge(id);
        revoke("payments.refund");
        assertThat(
            request("POST", "/api/payments/" + id + "/refunds", admin, Map.of("chargeId", charge, "providerReference",
                "r1", "amountMinor", 500, "refundedOn", LocalDate.now().toString(), "reason", "Confirmed")).status())
            .isEqualTo(403);
        revoke("payments.manage");
        assertThat(request("GET", "/api/payments", admin, null).status()).isEqualTo(403);
        assertThat(request("GET", "/api/payments", null, null).status()).isEqualTo(401);
    }
    @Test
    void invalidSignaturesCannotEnterTheLedger() throws Exception {
        UUID id = order("payfast", false);
        when(gateway.verify(anyString(), anyString(), any(), anyString()))
            .thenThrow(org.onechurch.payments.domain.PaymentException.invalid("Invalid signature"));
        assertThat(request("POST", "/api/payments/webhooks/payfast", null, Map.of()).status()).isEqualTo(400);
        assertThat(db.fetchCount(PAYMENTS_NOTIFICATIONS)).isZero();
        assertThat(db.fetchCount(GIVING_GIFTS)).isZero();
    }
    @Test
    void concurrentCallbacksDeduplicateByProviderReference() throws Exception {
        UUID id = order("payfast", false);
        when(gateway.verify(anyString(), anyString(), any(), anyString())).thenReturn(new PaymentGateway.Notification(
            "payfast", "live", "concurrent", "PAID", "same", null, id, 10000, "ZAR", null, LocalDate.now()));
        CompletableFuture<Response> one = CompletableFuture.supplyAsync(() -> notifyProvider()),
            two = CompletableFuture.supplyAsync(() -> notifyProvider());
        assertThat(one.join().status()).isEqualTo(204);
        assertThat(two.join().status()).isEqualTo(204);
        assertThat(db.fetchCount(GIVING_GIFTS)).isEqualTo(1);
    }

    @Test
    void aRefundVoidsAcknowledgementReleasesPledgeCreditAndAppearsOnStatement() throws Exception {
        UUID order = order("payfast", false);
        event(order, "PAID", "original", 10000, null);
        UUID gift = charge(order);
        UUID donor = db.select(PAYMENTS_ORDERS.DONOR_ID)
            .from(PAYMENTS_ORDERS)
            .where(PAYMENTS_ORDERS.ID.eq(order))
            .fetchOne(PAYMENTS_ORDERS.DONOR_ID);
        UUID fund = db.select(PAYMENTS_ORDERS.FUND_ID)
            .from(PAYMENTS_ORDERS)
            .where(PAYMENTS_ORDERS.ID.eq(order))
            .fetchOne(PAYMENTS_ORDERS.FUND_ID);
        UUID acknowledgement = created(request("POST", GIVING + "/gifts/" + gift + "/acknowledgements", admin,
            Map.of("requestId", UUID.randomUUID())));
        UUID pledge = created(request("POST", GIVING + "/pledges", admin,
            Map.of("requestId", UUID.randomUUID(), "donorId", donor, "fundId", fund, "amountMinor", 10000, "currency",
                "ZAR", "startsOn", LocalDate.now().minusDays(1).toString(), "endsOn",
                LocalDate.now().plusDays(1).toString())));
        created(request("POST", GIVING + "/pledges/" + pledge + "/assignments", admin,
            Map.of("requestId", UUID.randomUUID(), "giftId", gift, "amountMinor", 10000, "expectedVersion", 0)));
        event(order, "REFUNDED", "part-refund", 2500, null);
        assertThat(request("GET", GIVING + "/acknowledgements/" + acknowledgement, admin, null).body())
            .contains("\"voided\":true");
        assertThat(request("GET", GIVING + "/pledges/" + pledge, admin, null).body()).contains("\"creditedMinor\":0",
            "CANCELLED", "Source gift refunded");
        assertThat(request("GET", GIVING + "/pledges/" + pledge + "/candidates", admin, null).body())
            .contains("\"availableMinor\":7500");
        assertThat(
            request("GET", GIVING + "/statements/" + donor + "?from=" + LocalDate.now() + "&to=" + LocalDate.now(),
                admin, null).body())
            .contains("REFUND", "-2500", "\"netMinor\":\"7500\"");
        assertThat(request("POST", GIVING + "/pledges/" + pledge + "/assignments", admin,
            Map.of("requestId", UUID.randomUUID(), "giftId", gift, "amountMinor", 7501, "expectedVersion", 2)).status())
            .isEqualTo(409);
        created(request("POST", GIVING + "/pledges/" + pledge + "/assignments", admin,
            Map.of("requestId", UUID.randomUUID(), "giftId", gift, "amountMinor", 7500, "expectedVersion", 2)));
    }

    @Test
    void pendingCancellationBlocksResumingCheckoutButRetainsInFlightPayments() throws Exception {
        UUID id = order("payfast", true);
        assertThat(request("POST", "/api/payments/" + id + "/subscription", admin,
            Map.of("expectedVersion", 0, "cancel", true)).status()).isEqualTo(409);
        assertThat(request("POST", "/api/payments/" + id + "/checkout", admin, null).status()).isEqualTo(409);
        event(id, "PAID", "in-flight-pending", 10000, "12345678-1234-1234-1234-123456789012");
        assertThat(db.fetchCount(GIVING_GIFTS)).isEqualTo(1);
        assertThat(request("GET", "/api/payments/" + id, admin, null).body()).contains("CANCEL_REQUESTED");
    }
    @Test
    void caseSensitiveProviderReferencesRetainDistinctRealCollections() throws Exception {
        UUID id = order("payfast", false);
        event(id, "PAID", "charge_ABC", 10000, null);
        event(id, "PAID", "charge_abc", 10000, null);
        assertThat(db.fetchCount(GIVING_GIFTS)).isEqualTo(2);
        event(id, "FAILED", "late_failure", 10000, null);
        assertThat(request("GET", "/api/payments/" + id, admin, null).body()).contains("PAID");
    }
    private Response notifyProvider() {
        try {
            return request("POST", "/api/payments/webhooks/payfast", null, Map.of());
        } catch (Exception e) {
            throw new IllegalStateException(e);
        }
    }
    @Test
    void exactCentsAndReadOnlyEndpointsAreEnforced() throws Exception {
        UUID id = order("payfast", false);
        assertThat(request("GET", "/api/payments?limit=101", admin, null).status()).isEqualTo(400);
        int count = db.fetchCount(PAYMENTS_NOTIFICATIONS);
        request("GET", "/api/payments", admin, null);
        request("GET", "/api/payments/" + id, admin, null);
        assertThat(db.fetchCount(PAYMENTS_NOTIFICATIONS)).isEqualTo(count);
        assertThat(db.fetchCount(GIVING_GIFTS)).isZero();
        assertThat(request("POST", "/api/payments", admin, Map.of("requestId", UUID.randomUUID(), "donorId",
            person("Fraction"), "fundId", fund("Other"), "amountMinor", 500.5, "provider", "yoco")).status())
            .isEqualTo(400);
    }
}
