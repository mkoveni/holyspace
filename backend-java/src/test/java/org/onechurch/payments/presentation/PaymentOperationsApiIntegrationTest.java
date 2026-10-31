package org.onechurch.payments.presentation;

import static org.assertj.core.api.Assertions.assertThat;
import static org.mockito.ArgumentMatchers.any;
import static org.mockito.ArgumentMatchers.anyLong;
import static org.mockito.ArgumentMatchers.anyString;
import static org.mockito.Mockito.doThrow;
import static org.mockito.Mockito.never;
import static org.mockito.Mockito.verify;
import static org.mockito.Mockito.when;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_GIFTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_CHARGES;
import static org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_ORDERS;
import static org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_RECEIPTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.PEOPLE_CONTACT_METHODS;

import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.onechurch.communications.application.DeliveryProcessor;
import org.onechurch.communications.application.contract.DeliveryReader;
import org.onechurch.communications.application.contract.DeliverySnapshot;
import org.onechurch.communications.application.contract.EmailTransport;
import org.onechurch.giving.application.contract.OnlineGivingLedger;
import org.onechurch.payments.application.contract.PaymentGateway;
import org.onechurch.payments.infrastructure.PaymentDeliveryEligibility;
import org.onechurch.shared.infrastructure.PaymentIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.beans.factory.annotation.Qualifier;
import org.springframework.test.context.bean.override.mockito.MockitoBean;
import org.springframework.test.context.bean.override.mockito.MockitoSpyBean;

import java.time.Instant;
import java.time.LocalDate;
import java.util.Map;
import java.util.UUID;

class PaymentOperationsApiIntegrationTest extends PaymentIntegrationTestSupport {
    @MockitoBean PaymentGateway gateway;
    @MockitoBean EmailTransport transport;
    @MockitoBean org.onechurch.communications.infrastructure.DeliveryScheduler scheduler;
    @MockitoSpyBean OnlineGivingLedger giving;
    @Autowired PaymentDeliveryEligibility eligibility;
    @Autowired DeliveryProcessor processor;
    @Autowired
    @Qualifier("communicationDeliveryStore") DeliveryReader deliveries;
    private UUID donor;
    @BeforeEach
    void setupProvider() {
        when(gateway.mode(anyString())).thenReturn("live");
        when(transport.configuration()).thenReturn(new EmailTransport.Configuration(false, true, "capture"));
        when(transport.send(any())).thenReturn(new EmailTransport.Result("SENT", "", true));
    }
    private UUID order(String mode) throws Exception {
        when(gateway.mode(anyString())).thenReturn(mode);
        donor = person("Receipt donor");
        return created(request("POST", "/api/payments", admin, Map.of("requestId", UUID.randomUUID(), "donorId", donor,
            "fundId", fund("General"), "amountMinor", 10000, "provider", "payfast")));
    }
    private void paid(UUID id, String mode) throws Exception {
        when(gateway.verify(anyString(), anyString(), any(), anyString())).thenReturn(new PaymentGateway.Notification(
            "payfast", mode, "evt_" + id, "PAID", "charge_" + id, null, id, 10000, "ZAR", null, LocalDate.now()));
        assertThat(request("POST", "/api/payments/webhooks/payfast", null, Map.of("event", "fixture")).status())
            .isEqualTo(204);
    }
    private UUID charge(UUID id) {
        return db.select(PAYMENTS_CHARGES.ID)
            .from(PAYMENTS_CHARGES)
            .where(PAYMENTS_CHARGES.ORDER_ID.eq(id))
            .fetchOne(PAYMENTS_CHARGES.ID);
    }
    private long version(UUID id) {
        return db.select(PAYMENTS_ORDERS.VERSION)
            .from(PAYMENTS_ORDERS)
            .where(PAYMENTS_ORDERS.ID.eq(id))
            .fetchOne(PAYMENTS_ORDERS.VERSION);
    }
    private UUID contact() throws Exception {
        return created(request("POST", "/api/people/" + donor + "/contacts", admin,
            Map.of("expectedVersion", 0, "kind", "EMAIL", "value", "receipt@example.org", "primary", true)));
    }
    private Response preview(UUID order, UUID charge, UUID contact) throws Exception {
        return request("GET", "/api/payments/" + order + "/receipts?chargeId=" + charge + "&contactId=" + contact,
            admin, null);
    }
    @SuppressWarnings("unchecked")
    private String hash(Response response) {
        return (String) ((Map<String, Object>) response.data().get("preview")).get("hash");
    }
    @Test
    void aChangedContactRequiresANewPreviewBeforeApproval() throws Exception {
        UUID id = order("live");
        paid(id, "live");
        UUID charge = charge(id), contact = contact();
        Response preview = preview(id, charge, contact);
        db.update(PEOPLE_CONTACT_METHODS)
            .set(PEOPLE_CONTACT_METHODS.VALUE, "updated@example.org")
            .where(PEOPLE_CONTACT_METHODS.ID.eq(contact))
            .execute();
        Response stale = request("POST", "/api/payments/" + id + "/receipts", admin,
            Map.of("requestId", UUID.randomUUID(), "chargeId", charge, "contactId", contact, "reviewHash",
                hash(preview), "evidence", "Donor confirmed address"));
        assertThat(stale.status()).isEqualTo(409);
        assertThat(db.fetchCount(PAYMENTS_RECEIPTS)).isZero();
    }
    @Test
    void captureIsRecordedWithoutClaimingActualDeliveryAndDispatchCannotDuplicate() throws Exception {
        UUID id = order("live");
        paid(id, "live");
        UUID charge = charge(id), contact = contact();
        UUID delivery = created(request("POST", "/api/payments/" + id + "/receipts", admin,
            Map.of("requestId", UUID.randomUUID(), "chargeId", charge, "contactId", contact, "reviewHash",
                hash(preview(id, charge, contact)), "evidence", "Verified by donor")));
        processor.dispatch(delivery);
        processor.dispatch(delivery);
        DeliverySnapshot sent = deliveries.delivery(delivery).orElseThrow();
        assertThat(sent.status()).isEqualTo("SENT");
        assertThat(sent.captured()).isTrue();
        verify(transport).send(any());
        assertThat(request("GET", "/api/payments/" + id + "/receipts?chargeId=" + charge, admin, null).body())
            .contains("\"captured\":true")
            .doesNotContain("DELIVERED");
    }
    @Test
    void failedPostingCanBeRetriedWithoutSettlementOrAnotherCheckoutAndNeverDuplicatesGiving() throws Exception {
        UUID id = order("live");
        doThrow(new IllegalStateException("temporary ledger failure")).doCallRealMethod()
            .when(giving)
            .post(any(), any(), any(), anyLong(), anyString(), any(), any());
        paid(id, "live");
        assertThat(db.fetchCount(GIVING_GIFTS)).isZero();
        assertThat(request("GET", "/api/payments/" + id, admin, null).body()).contains("POSTING_PENDING");
        Response retried =
            request("POST", "/api/payments/" + id + "/posting-retries", admin, Map.of("expectedVersion", version(id)));
        assertThat(retried.status()).withFailMessage(retried.body()).isEqualTo(200);
        assertThat(retried.body()).contains("\"complete\":true");
        assertThat(db.fetchCount(GIVING_GIFTS)).isEqualTo(1);
        assertThat(
            request("POST", "/api/payments/" + id + "/posting-retries", admin, Map.of("expectedVersion", version(id)))
                .status())
            .isEqualTo(200);
        assertThat(db.fetchCount(GIVING_GIFTS)).isEqualTo(1);
        verify(gateway, never()).checkout(any(), anyString(), anyString());
        assertThat(request("GET", "/api/payments/" + id, admin, null).body()).contains("POSTED", "NEEDS_REVIEW");
    }
    @Test
    void simulationsNeverPostOrNeedBankSettlementOrSendDonorConfirmations() throws Exception {
        UUID id = order("test");
        paid(id, "test");
        UUID charge = charge(id);
        Response detail = request("GET", "/api/payments/" + id, admin, null);
        assertThat(detail.body()).contains("SIMULATION", "NOT_APPLICABLE", "\"needsReview\":false");
        assertThat(
            request("POST", "/api/payments/" + id + "/posting-retries", admin, Map.of("expectedVersion", version(id)))
                .status())
            .isEqualTo(409);
        assertThat(request("GET", "/api/payments/" + id + "/receipts?chargeId=" + charge, admin, null).status())
            .isEqualTo(409);
        assertThat(db.fetchCount(GIVING_GIFTS)).isZero();
    }
    @Test
    void reviewedConfirmationIsFrozenIdempotentAndContactChangesBlockDispatch() throws Exception {
        UUID id = order("live");
        paid(id, "live");
        UUID charge = charge(id), contact = contact(), requestId = UUID.randomUUID();
        Response preview = preview(id, charge, contact);
        assertThat(preview.status()).withFailMessage(preview.body()).isEqualTo(200);
        assertThat(preview.body()).contains("ZAR 100.00", "not a tax certificate");
        Map<String, Object> body = Map.of("requestId", requestId, "chargeId", charge, "contactId", contact,
            "reviewHash", hash(preview), "evidence", "Donor personally confirmed this address");
        UUID delivery = created(request("POST", "/api/payments/" + id + "/receipts", admin, body));
        assertThat(created(request("POST", "/api/payments/" + id + "/receipts", admin, body))).isEqualTo(delivery);
        assertThat(db.fetchCount(PAYMENTS_RECEIPTS)).isEqualTo(1);
        DeliverySnapshot frozen = deliveries.delivery(delivery).orElseThrow();
        assertThat(eligibility.eligible(frozen, Instant.now())).isTrue();
        db.update(PEOPLE_CONTACT_METHODS)
            .set(PEOPLE_CONTACT_METHODS.VALUE, "changed@example.org")
            .where(PEOPLE_CONTACT_METHODS.ID.eq(contact))
            .execute();
        assertThat(eligibility.eligible(frozen, Instant.now())).isFalse();
        processor.dispatch(delivery);
        assertThat(deliveries.delivery(delivery).orElseThrow().status()).isEqualTo("BLOCKED");
        verify(transport, never()).send(any());
        assertThat(request("GET", "/api/payments/" + id + "/receipts?chargeId=" + charge, admin, null).body())
            .contains("receipt@example.org", "BLOCKED");
    }
    @Test
    void financialAccessDoesNotGrantReceiptSendingAndUnrelatedContactsAreRejected() throws Exception {
        UUID id = order("live");
        paid(id, "live");
        UUID charge = charge(id);
        assertThat(preview(id, charge, UUID.randomUUID()).status()).isEqualTo(409);
        revoke("payments.receipts.send");
        assertThat(request("GET", "/api/payments/" + id + "/receipts?chargeId=" + charge, admin, null).status())
            .isEqualTo(403);
        assertThat(db.fetchCount(PAYMENTS_RECEIPTS)).isZero();
    }
}
