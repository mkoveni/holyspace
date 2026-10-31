package org.onechurch.payments.presentation;
import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;
import static org.mockito.ArgumentMatchers.any;
import static org.mockito.ArgumentMatchers.anyString;
import static org.mockito.Mockito.when;
import static org.onechurch.shared.infrastructure.jooq.Tables.ACCOUNT_SESSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_DONOR_LINKS;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_DONOR_LINK_HISTORY;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_GIFTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_CHARGES;
import static org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_ORDERS;
import static org.onechurch.shared.infrastructure.jooq.Tables.USER_ACCOUNTS;

import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.onechurch.giving.domain.GivingException;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.payments.application.command.startdonorpayment.StartDonorPaymentCommand;
import org.onechurch.payments.application.command.startdonorpayment.StartDonorPaymentHandler;
import org.onechurch.payments.application.contract.PaymentGateway;
import org.onechurch.payments.application.query.getmygiving.GetMyGivingHandler;
import org.onechurch.payments.application.query.getmygiving.GetMyGivingQuery;
import org.onechurch.shared.infrastructure.PaymentIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.test.context.bean.override.mockito.MockitoBean;

import java.time.LocalDate;
import java.util.List;
import java.util.Map;
import java.util.UUID;
@org.springframework.test.context.TestPropertySource(properties = "PAYMENTS_TOKEN_KEY=AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=")
class DonorGivingApiIntegrationTest extends PaymentIntegrationTestSupport {
    @MockitoBean PaymentGateway gateway;
    @MockitoBean org.onechurch.communications.infrastructure.DeliveryScheduler scheduler;
    @Autowired StartDonorPaymentHandler start;
    @Autowired GetMyGivingHandler overview;
    private UUID donor, account, fund;
    private String token;
    @BeforeEach
    void setupDonor() throws Exception {
        db.truncate(GIVING_DONOR_LINKS).cascade().execute();
        donor = person("Donor");
        account = createUser("donor", "donor@example.org", "Donor");
        token = login("donor", PASSWORD);
        fund = fund("General");
        when(gateway.mode(anyString())).thenReturn("test");
        when(gateway.providers())
            .thenReturn(List.of(new PaymentGateway.Provider("payfast", "Payfast", true, "test", true)));
        when(gateway.checkout(any(), anyString(), anyString())).thenReturn(new PaymentGateway.Checkout("checkout",
            "https://sandbox.payfast.co.za/eng/process", Map.of("merchant_id", "fixture")));
    }
    private void link(boolean active, long version) throws Exception {
        Response r = request("PUT", "/api/giving/donor-access/" + account, admin, Map.of("personId", donor, "active",
            active, "expectedVersion", version, "evidence", "Verified account holder identity in person"));
        assertThat(r.status()).withFailMessage(r.body()).isEqualTo(204);
    }
    private void offer(boolean enabled, long version) throws Exception {
        Response r = request("PUT", "/api/giving/online-funds/" + fund, admin,
            Map.of("enabled", enabled, "expectedVersion", version));
        assertThat(r.status()).withFailMessage(r.body()).isEqualTo(204);
    }
    private Map<String, Object> body(UUID id, boolean recurring) {
        return Map.of("requestId", id, "fundId", fund, "amountMinor", 10000, "provider", "payfast", "recurring",
            recurring);
    }
    private UUID ownOrder(boolean monthly) throws Exception {
        return created(request("POST", "/api/my-giving/payments", token, body(UUID.randomUUID(), monthly)));
    }
    private long version(UUID id) {
        return db.select(PAYMENTS_ORDERS.VERSION)
            .from(PAYMENTS_ORDERS)
            .where(PAYMENTS_ORDERS.ID.eq(id))
            .fetchOne(PAYMENTS_ORDERS.VERSION);
    }
    private void paid(UUID id, String mode, String subscription) throws Exception {
        when(gateway.verify(anyString(), anyString(), any(), anyString()))
            .thenReturn(new PaymentGateway.Notification("payfast", mode, "event_" + id, "PAID", "charge_" + id, null,
                id, 10000, "ZAR", subscription, LocalDate.now()));
        assertThat(request("POST", "/api/payments/webhooks/payfast", null, Map.of("fixture", true)).status())
            .isEqualTo(204);
    }
    @Test
    void unlinkedAccountsNeverGainFinancialAccessFromMatchingEmail() throws Exception {
        assertThat(request("GET", "/api/my-giving", null, null).status()).isEqualTo(401);
        Response unlinked = request("GET", "/api/my-giving", token, null);
        assertThat(unlinked.status()).isEqualTo(200);
        assertThat(unlinked.data().get("donor")).isNull();
        assertThat(request("POST", "/api/my-giving/payments", token, body(UUID.randomUUID(), false)).status())
            .isEqualTo(403);
        assertThat(request("GET", "/api/payments", token, null).status()).isEqualTo(403);
        assertThat(request("GET", "/api/giving/gifts?from=2026-01-01&to=2026-12-31", token, null).status())
            .isEqualTo(403);
        assertThat(request("PUT", "/api/giving/donor-access/" + account, token,
            Map.of("personId", donor, "active", true, "expectedVersion", 0, "evidence", "self claim")).status())
            .isEqualTo(403);
    }
    @Test
    void onlineFundsRequireExplicitOptInAndCurrentVersion() throws Exception {
        link(true, 0);
        assertThat(request("POST", "/api/my-giving/payments", token, body(UUID.randomUUID(), false)).status())
            .isEqualTo(400);
        offer(true, 0);
        ownOrder(false);
        assertThat(
            request("PUT", "/api/giving/online-funds/" + fund, admin, Map.of("enabled", false, "expectedVersion", 0))
                .status())
            .isEqualTo(409);
        offer(false, 1);
        assertThat(request("POST", "/api/my-giving/payments", token, body(UUID.randomUUID(), false)).status())
            .isEqualTo(400);
    }
    @Test
    void donorIsResolvedServerSideAndRetryCannotDuplicateOrChangeIntent() throws Exception {
        link(true, 0);
        offer(true, 0);
        UUID id = UUID.randomUUID();
        assertThat(created(request("POST", "/api/my-giving/payments", token, body(id, false)))).isEqualTo(id);
        assertThat(created(request("POST", "/api/my-giving/payments", token, body(id, false)))).isEqualTo(id);
        assertThat(db.fetchCount(PAYMENTS_ORDERS)).isEqualTo(1);
        assertThat(db.select(PAYMENTS_ORDERS.DONOR_ID).from(PAYMENTS_ORDERS).fetchOne(PAYMENTS_ORDERS.DONOR_ID))
            .isEqualTo(donor);
        assertThat(db.select(PAYMENTS_ORDERS.SELF_SERVICE).from(PAYMENTS_ORDERS).fetchOne(PAYMENTS_ORDERS.SELF_SERVICE))
            .isTrue();
        Map<String, Object> changed = new java.util.HashMap<>(body(id, false));
        changed.put("amountMinor", 20000);
        assertThat(request("POST", "/api/my-giving/payments", token, changed).status()).isEqualTo(409);
        assertThat(request("POST", "/api/my-giving/payments/" + id + "/checkout", token, null).status()).isEqualTo(200);
        assertThat(request("POST", "/api/my-giving/payments/" + id + "/checkout", token, null).status()).isEqualTo(200);
    }
    @Test
    void anotherDonorCannotReadCheckoutOrCancelAnyonesOrder() throws Exception {
        link(true, 0);
        offer(true, 0);
        UUID id = ownOrder(true);
        UUID second = createUser("second", "second@example.org", "Other donor"), person = person("Other");
        String other = login("second", PASSWORD);
        assertThat(request("PUT", "/api/giving/donor-access/" + second, admin,
            Map.of("personId", person, "active", true, "expectedVersion", 0, "evidence", "Verified other identity"))
            .status()).isEqualTo(204);
        assertThat(request("GET", "/api/my-giving/payments/" + id, other, null).status()).isEqualTo(404);
        assertThat(request("POST", "/api/my-giving/payments/" + id + "/checkout", other, null).status()).isEqualTo(404);
        assertThat(request("POST", "/api/my-giving/payments/" + id + "/subscription", other,
            Map.of("expectedVersion", 0, "cancel", true)).status()).isEqualTo(404);
        assertThat(request("GET", "/api/my-giving", other, null).body()).doesNotContain(id.toString());
    }
    @Test
    void verifiedHistoryContainsOnlyOwnGiftsAndSafePaymentFields() throws Exception {
        link(true, 0);
        offer(true, 0);
        when(gateway.mode(anyString())).thenReturn("live");
        UUID id = ownOrder(false);
        paid(id, "live", null);
        gift(fund, LocalDate.now(), 5000);
        Response page = request("GET", "/api/my-giving", token, null);
        assertThat(page.status()).withFailMessage(page.body()).isEqualTo(200);
        assertThat(((Number) page.data().get("giftTotal")).longValue()).isEqualTo(1);
        Response detail = request("GET", "/api/my-giving/payments/" + id, token, null);
        assertThat(detail.body()).contains("POSTED", "NOT_ISSUED")
            .doesNotContain("encryptedToken", "checkoutFields", "reviewReason", "settlement", "actorId",
                "Awaiting settlement review");
        assertThat(db.fetchCount(GIVING_GIFTS)).isEqualTo(2);
    }
    @Test
    void simulationsRemainSeparateFromActualGivingHistory() throws Exception {
        link(true, 0);
        offer(true, 0);
        UUID id = ownOrder(false);
        paid(id, "test", null);
        assertThat(request("GET", "/api/my-giving/payments/" + id, token, null).body()).contains("SIMULATION");
        assertThat(((Number) request("GET", "/api/my-giving", token, null).data().get("giftTotal")).longValue())
            .isZero();
    }
    @Test
    void cancellationWaitsForProviderAndNeverInventsARefund() throws Exception {
        link(true, 0);
        offer(true, 0);
        UUID id = ownOrder(true);
        assertThat(request("POST", "/api/my-giving/payments/" + id + "/subscription", token,
            Map.of("expectedVersion", version(id), "cancel", true)).status()).isEqualTo(409);
        assertThat(request("GET", "/api/my-giving/payments/" + id, token, null).body()).contains("CANCEL_REQUESTED");
        paid(id, "test", "confirmed-subscription-token");
        when(gateway.subscription(any(), anyString(), any(Boolean.class))).thenReturn("CANCELLED");
        assertThat(request("POST", "/api/my-giving/payments/" + id + "/subscription", token,
            Map.of("expectedVersion", version(id), "cancel", true)).status()).isEqualTo(204);
        assertThat(request("GET", "/api/my-giving/payments/" + id, token, null).body()).contains("CANCELLED");
    }
    @Test
    void revocationIsImmediateVersionedAndIdentityCannotBeReassigned() throws Exception {
        link(true, 0);
        offer(true, 0);
        UUID id = ownOrder(false);
        UUID other = person("Another");
        assertThat(request("PUT", "/api/giving/donor-access/" + account, admin,
            Map.of("personId", other, "active", true, "expectedVersion", 1, "evidence", "Wrong reassignment")).status())
            .isEqualTo(409);
        link(false, 1);
        assertThat(request("GET", "/api/my-giving", token, null).data().get("donor")).isNull();
        assertThat(request("POST", "/api/my-giving/payments/" + id + "/checkout", token, null).status()).isEqualTo(403);
        assertThat(db.fetchCount(GIVING_DONOR_LINK_HISTORY)).isEqualTo(2);
        link(true, 2);
        assertThat(request("GET", "/api/my-giving/payments/" + id, token, null).status()).isEqualTo(200);
    }
    @Test
    void disabledAccountsAndRevokedSessionsCannotUseAnExistingLink() throws Exception {
        link(true, 0);
        offer(true, 0);
        db.deleteFrom(ACCOUNT_SESSIONS).where(ACCOUNT_SESSIONS.ACCOUNT_ID.eq(account)).execute();
        assertThat(request("GET", "/api/my-giving", token, null).status()).isEqualTo(401);
        token = login("donor", PASSWORD);
        db.update(USER_ACCOUNTS).set(USER_ACCOUNTS.STATUS, "disabled").where(USER_ACCOUNTS.ID.eq(account)).execute();
        assertThat(request("GET", "/api/my-giving", token, null).status()).isEqualTo(401);
    }
    @Test
    void linkUniquenessAndBothAdministrativePermissionsAreRequired() throws Exception {
        link(true, 0);
        UUID other = createUser("other", "other@example.org", "Other");
        assertThat(request("PUT", "/api/giving/donor-access/" + other, admin,
            Map.of("personId", donor, "active", true, "expectedVersion", 0, "evidence", "Duplicate person")).status())
            .isEqualTo(409);
        revoke("giving.manage");
        assertThat(request("GET", "/api/giving/donor-access", admin, null).status()).isEqualTo(403);
    }
    @Test
    @SuppressWarnings("unchecked")
    void donorCanViewFrozenConfirmationWithoutRecipientOrApprovalEvidence() throws Exception {
        link(true, 0);
        offer(true, 0);
        when(gateway.mode(anyString())).thenReturn("live");
        UUID id = ownOrder(false);
        paid(id, "live", null);
        UUID contact = created(request("POST", "/api/people/" + donor + "/contacts", admin,
            Map.of("expectedVersion", 0, "kind", "EMAIL", "value", "verified-donor@example.org", "primary", true)));
        UUID charge = db.select(PAYMENTS_CHARGES.ID)
            .from(PAYMENTS_CHARGES)
            .where(PAYMENTS_CHARGES.ORDER_ID.eq(id))
            .fetchOne(PAYMENTS_CHARGES.ID);
        Response preview = request("GET",
            "/api/payments/" + id + "/receipts?chargeId=" + charge + "&contactId=" + contact, admin, null);
        String hash = (String) ((Map<String, Object>) preview.data().get("preview")).get("hash");
        created(request("POST", "/api/payments/" + id + "/receipts", admin,
            Map.of("requestId", UUID.randomUUID(), "chargeId", charge, "contactId", contact, "reviewHash", hash,
                "evidence", "Private staff identity evidence")));
        Response detail = request("GET", "/api/my-giving/payments/" + id, token, null);
        assertThat(detail.status()).withFailMessage(detail.body()).isEqualTo(200);
        assertThat(detail.body()).contains("confirmation", "Thank you", "not a tax certificate", "QUEUED")
            .doesNotContain("verified-donor@example.org", "Private staff identity evidence", "contactId", "approvedBy");
    }
    @Test
    void directHandlersCheckLiveSessionAndRejectActorSpoofing() {
        assertIdentityFailure(() -> overview.handle(new GetMyGivingQuery(25, 0, 0)),
            IdentityException.Kind.UNAUTHENTICATED);
        asActor(token, () -> {
            assertThatThrownBy(() -> start
                .handle(new StartDonorPaymentCommand(adminId, UUID.randomUUID(), fund, 10000, "payfast", false)))
                .isInstanceOf(GivingException.class);
            db.update(ACCOUNT_SESSIONS)
                .set(ACCOUNT_SESSIONS.EXPIRES_AT, java.time.OffsetDateTime.now().minusSeconds(1))
                .where(ACCOUNT_SESSIONS.ACCOUNT_ID.eq(account))
                .execute();
            assertIdentityFailure(() -> overview.handle(new GetMyGivingQuery(25, 0, 0)),
                IdentityException.Kind.UNAUTHENTICATED);
            return null;
        });
    }
}
