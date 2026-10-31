package org.onechurch.communications.presentation;
import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;
import static org.onechurch.shared.infrastructure.jooq.Tables.ACCOUNT_SESSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNICATION_PUSH_DELIVERIES;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNICATION_PUSH_DEVICES;
import static org.onechurch.shared.infrastructure.jooq.Tables.USER_ACCOUNTS;

import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.onechurch.communications.application.PushDeliveryProcessor;
import org.onechurch.communications.application.contract.MemberNotifications;
import org.onechurch.communications.application.contract.PushTransport;
import org.onechurch.shared.application.UnitOfWork;
import org.onechurch.shared.infrastructure.IdentityIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.context.TestConfiguration;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Import;
import org.springframework.context.annotation.Primary;
import org.springframework.test.context.TestPropertySource;
import org.springframework.transaction.support.TransactionSynchronizationManager;

import java.time.OffsetDateTime;
import java.util.Map;
import java.util.UUID;
@Import(MemberPushApiIntegrationTest.FakeConfiguration.class)
@TestPropertySource(properties = {"PUSH_ENABLED=true", "PUSH_PROJECT_ID=11111111-1111-4111-8111-111111111111",
    "PUSH_TOKEN_KEY=MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=", "PUSH_POLL_MS=86400000"})
class MemberPushApiIntegrationTest extends IdentityIntegrationTestSupport {
    private static final String ROOT = "/api/member/push";
    private static final String TOKEN = "ExponentPushToken[test_device_token_12345]";
    private static final String SECRET = "installation_secret_that_is_long_enough_123456789";
    private static final String PROJECT = "11111111-1111-4111-8111-111111111111";
    private final UUID device = UUID.randomUUID();
    @Autowired MemberNotifications notices;
    @Autowired PushDeliveryProcessor processor;
    @Autowired FakeTransport transport;
    @Autowired UnitOfWork tx;
    @BeforeEach
    void resetTransport() {
        transport.calls = 0;
        transport.inTransaction = false;
        transport.send = new PushTransport.Result("ACCEPTED", "ticket-1", "");
        transport.receipt = new PushTransport.Result("PROVIDER_ACCEPTED", "ticket-1", "");
    }
    private Map<String, Object> registration(String secret, String token) {
        return Map.of("installationSecret", secret, "token", token, "platform", "android", "projectId", PROJECT);
    }
    private void enable() throws Exception {
        assertThat(request("PUT", ROOT + "/devices/" + device, admin, registration(SECRET, TOKEN)).status())
            .isEqualTo(204);
        assertThat(
            request("PUT", ROOT + "/preferences/VOLUNTEERING", admin, Map.of("enabled", true, "expectedVersion", 0))
                .status())
            .isEqualTo(204);
    }
    private UUID publish(String key) {
        notices.publish(adminId, MemberNotifications.Kind.VOLUNTEER_INVITATION, key);
        return db.selectFrom(COMMUNICATION_PUSH_DELIVERIES)
            .orderBy(COMMUNICATION_PUSH_DELIVERIES.CREATED_AT.desc())
            .limit(1)
            .fetchOne()
            .getId();
    }
    private String state(UUID job) {
        return db.selectFrom(COMMUNICATION_PUSH_DELIVERIES)
            .where(COMMUNICATION_PUSH_DELIVERIES.ID.eq(job))
            .fetchSingle()
            .getStatus();
    }
    @Test
    void registrationIsPrivateSessionBoundAndInstallationSecretProtected() throws Exception {
        assertThat(request("GET", ROOT, null, null).status()).isEqualTo(401);
        assertThat(request("GET", ROOT, admin, null).body()).contains("\"enabled\":false", "\"available\":true");
        enable();
        Response own = request("GET", ROOT, admin, null);
        assertThat(own.body()).contains(device.toString(), "android", "currentSession")
            .doesNotContain(TOKEN, SECRET, "secretHash", "tokenHash", "sessionId", "encryptedToken");
        assertThat(db.selectFrom(COMMUNICATION_PUSH_DEVICES).fetchSingle().getEncryptedToken()).doesNotContain(TOKEN);
        long version = db.selectFrom(COMMUNICATION_PUSH_DEVICES).fetchSingle().getRevision();
        assertThat(request("PUT", ROOT + "/devices/" + device, admin, registration(SECRET, TOKEN)).status())
            .isEqualTo(204);
        assertThat(db.selectFrom(COMMUNICATION_PUSH_DEVICES).fetchSingle().getRevision()).isEqualTo(version);
        assertThat(request("PUT", ROOT + "/devices/" + device, admin,
            registration("wrong_installation_secret_but_long_enough_123456789", TOKEN)).status()).isEqualTo(403);
        UUID other = createUser("other", "other@example.org", "Other");
        String token = login("other", PASSWORD);
        assertThat(request("GET", ROOT, token, null).body()).doesNotContain(device.toString());
        assertThat(request("DELETE", ROOT + "/devices/" + device, token, null).status()).isEqualTo(404);
        assertThat(request("PUT", ROOT + "/devices/" + device, token, registration(SECRET, TOKEN)).status())
            .isEqualTo(409);
    }
    @Test
    void outboxIsAtomicDeduplicatedAndProviderAcceptanceIsSeparateFromReceipt() throws Exception {
        enable();
        UUID job = publish("one");
        publish("one");
        assertThat(db.fetchCount(COMMUNICATION_PUSH_DELIVERIES)).isEqualTo(1);
        assertThatThrownBy(() -> tx.execute(() -> {
            notices.publish(adminId, MemberNotifications.Kind.VOLUNTEER_INVITATION, "rollback");
            throw new IllegalStateException();
        })).isInstanceOf(IllegalStateException.class);
        assertThat(db.fetchCount(COMMUNICATION_PUSH_DELIVERIES)).isEqualTo(1);
        processor.dispatch(job);
        assertThat(state(job)).isEqualTo("TICKET");
        assertThat(transport.inTransaction).isFalse();
        processor.dispatch(job);
        assertThat(transport.calls).isEqualTo(1);
        db.update(COMMUNICATION_PUSH_DELIVERIES)
            .set(COMMUNICATION_PUSH_DELIVERIES.DUE_AT, OffsetDateTime.now().minusSeconds(1))
            .where(COMMUNICATION_PUSH_DELIVERIES.ID.eq(job))
            .execute();
        processor.dispatch(job);
        assertThat(state(job)).isEqualTo("PROVIDER_ACCEPTED");
        assertThat(transport.calls).isEqualTo(2);
    }
    @Test
    void optOutAndRemoteRevocationCancelQueuedWorkAndForeignPushCannotOpen() throws Exception {
        enable();
        UUID job = publish("one");
        UUID notice = db.selectFrom(COMMUNICATION_PUSH_DELIVERIES).fetchSingle().getNotificationId();
        createUser("other", "other@example.org", "Other");
        String other = login("other", PASSWORD);
        assertThat(request("GET", "/api/member/notifications/" + notice, other, null).status()).isEqualTo(404);
        assertThat(request("GET", "/api/member/notifications/" + notice, admin, null).body()).contains("VOLUNTEERING")
            .doesNotContain(TOKEN);
        assertThat(
            request("PUT", ROOT + "/preferences/VOLUNTEERING", admin, Map.of("enabled", false, "expectedVersion", 1))
                .status())
            .isEqualTo(204);
        assertThat(
            request("PUT", ROOT + "/preferences/VOLUNTEERING", admin, Map.of("enabled", true, "expectedVersion", 1))
                .status())
            .isEqualTo(409);
        processor.dispatch(job);
        assertThat(state(job)).isEqualTo("CANCELLED");
        assertThat(transport.calls).isZero();
        assertThat(
            request("PUT", ROOT + "/preferences/VOLUNTEERING", admin, Map.of("enabled", true, "expectedVersion", 2))
                .status())
            .isEqualTo(204);
        UUID next = publish("two");
        assertThat(request("DELETE", ROOT + "/devices/" + device, admin, null).status()).isEqualTo(204);
        processor.dispatch(next);
        assertThat(state(next)).isEqualTo("CANCELLED");
        assertThat(transport.calls).isZero();
    }
    @Test
    void expiredOrRevokedSessionsNeverSendAndOldAccountCannotReuseTheTap() throws Exception {
        enable();
        UUID job = publish("one");
        db.deleteFrom(ACCOUNT_SESSIONS).execute();
        processor.dispatch(job);
        assertThat(state(job)).isEqualTo("CANCELLED");
        assertThat(transport.calls).isZero();
    }
    @Test
    void tokenRotationCancelsOldJobsAndDeviceNotRegisteredRetiresOnlyCurrentRevision() throws Exception {
        enable();
        UUID old = publish("old");
        assertThat(request("PUT", ROOT + "/devices/" + device, admin,
            registration(SECRET, "ExpoPushToken[replacement_token_12345]")).status()).isEqualTo(204);
        processor.dispatch(old);
        assertThat(state(old)).isEqualTo("CANCELLED");
        UUID next = publish("new");
        transport.send = new PushTransport.Result("FAILED", null, "DeviceNotRegistered");
        processor.dispatch(next);
        assertThat(state(next)).isEqualTo("FAILED");
        assertThat(db.selectFrom(COMMUNICATION_PUSH_DEVICES).fetchSingle().getStatus()).isEqualTo("INVALID");
    }
    @Test
    void ambiguousTransportIsNotResubmittedButKnownRateLimitsAreRetried() throws Exception {
        enable();
        UUID unknown = publish("unknown");
        transport.send = new PushTransport.Result("UNKNOWN", null, "TRANSPORT_UNCERTAIN");
        processor.dispatch(unknown);
        processor.dispatch(unknown);
        assertThat(state(unknown)).isEqualTo("UNKNOWN");
        assertThat(transport.calls).isEqualTo(1);
        UUID retry = publish("rate");
        transport.send = new PushTransport.Result("RETRY", null, "RATE_LIMITED");
        processor.dispatch(retry);
        assertThat(state(retry)).isEqualTo("RETRY");
        processor.dispatch(retry);
        assertThat(transport.calls).isEqualTo(2);
    }
    @Test
    void preferencesAreIndependentAndGetNeverCreatesRegistrationsOrJobs() throws Exception {
        assertThat(request("GET", ROOT, admin, null).status()).isEqualTo(200);
        assertThat(db.fetchCount(COMMUNICATION_PUSH_DEVICES)).isZero();
        assertThat(db.fetchCount(COMMUNICATION_PUSH_DELIVERIES)).isZero();
        enable();
        assertThat(request("PUT", "/api/member/notification-preferences/VOLUNTEERING", admin,
            Map.of("enabled", false, "expectedVersion", 0)).status()).isEqualTo(204);
        UUID job = publish("push-only");
        assertThat(request("GET", "/api/member/notifications", admin, null).data()).containsEntry("unreadCount", 0);
        processor.dispatch(job);
        assertThat(state(job)).isEqualTo("TICKET");
    }
    @Test
    void disabledAccountsAndExpiredSessionsBlockDispatch() throws Exception {
        enable();
        UUID job = publish("disabled");
        db.update(USER_ACCOUNTS).set(USER_ACCOUNTS.STATUS, "disabled").where(USER_ACCOUNTS.ID.eq(adminId)).execute();
        processor.dispatch(job);
        assertThat(state(job)).isEqualTo("CANCELLED");
        assertThat(transport.calls).isZero();
        db.update(USER_ACCOUNTS).set(USER_ACCOUNTS.STATUS, "active").where(USER_ACCOUNTS.ID.eq(adminId)).execute();
        UUID expired = publish("expired");
        db.update(ACCOUNT_SESSIONS).set(ACCOUNT_SESSIONS.EXPIRES_AT, OffsetDateTime.now().minusSeconds(1)).execute();
        processor.dispatch(expired);
        assertThat(state(expired)).isEqualTo("CANCELLED");
        assertThat(transport.calls).isZero();
    }
    @Test
    void changingAccountCannotReceiveOrOpenThePreviousAccountsQueuedNotice() throws Exception {
        enable();
        UUID job = publish("old-account");
        UUID notice = db.selectFrom(COMMUNICATION_PUSH_DELIVERIES).fetchSingle().getNotificationId();
        createUser("other", "other@example.org", "Other");
        String other = login("other", PASSWORD);
        db.deleteFrom(ACCOUNT_SESSIONS).where(ACCOUNT_SESSIONS.ACCOUNT_ID.eq(adminId)).execute();
        assertThat(request("PUT", ROOT + "/devices/" + device, other, registration(SECRET, TOKEN)).status())
            .isEqualTo(204);
        assertThat(request("GET", "/api/member/notifications/" + notice, other, null).status()).isEqualTo(404);
        processor.dispatch(job);
        assertThat(state(job)).isEqualTo("CANCELLED");
        assertThat(transport.calls).isZero();
    }
    @Test
    void crashRecoveryNeverResendsAnUncertainSend() throws Exception {
        enable();
        UUID job = publish("crash");
        db.update(COMMUNICATION_PUSH_DELIVERIES)
            .set(COMMUNICATION_PUSH_DELIVERIES.STATUS, "SENDING")
            .set(COMMUNICATION_PUSH_DELIVERIES.CLAIM_ID, UUID.randomUUID())
            .set(COMMUNICATION_PUSH_DELIVERIES.LEASE_UNTIL, OffsetDateTime.now().minusMinutes(3))
            .where(COMMUNICATION_PUSH_DELIVERIES.ID.eq(job))
            .execute();
        processor.runDue();
        assertThat(state(job)).isEqualTo("UNKNOWN");
        assertThat(transport.calls).isZero();
    }
    @TestConfiguration
    static class FakeConfiguration {
        @Bean
        @Primary
        FakeTransport fakePushTransport() {
            return new FakeTransport();
        }
    }
    static class FakeTransport implements PushTransport {
        int calls;
        boolean inTransaction;
        Result send, receipt;
        public Result send(String token, UUID id) {
            calls++;
            inTransaction |= TransactionSynchronizationManager.isActualTransactionActive();
            return send;
        }
        public Result receipt(String ticket) {
            calls++;
            inTransaction |= TransactionSynchronizationManager.isActualTransactionActive();
            return receipt;
        }
    }
}
