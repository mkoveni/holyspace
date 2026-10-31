package org.onechurch.communications.presentation;
import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNICATION_MEMBER_NOTIFICATIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.communications.application.contract.MemberNotifications;
import org.onechurch.shared.application.UnitOfWork;
import org.onechurch.shared.infrastructure.IdentityIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.time.OffsetDateTime;
import java.util.List;
import java.util.Map;
import java.util.UUID;
class MemberInboxApiIntegrationTest extends IdentityIntegrationTestSupport {
    @Autowired MemberNotifications notices;
    @Autowired UnitOfWork tx;
    private static final String ROOT = "/api/member/notifications";
    private static final String PREFS = "/api/member/notification-preferences";
    private void publish(UUID account, String key) {
        notices.publish(account, MemberNotifications.Kind.VOLUNTEER_INVITATION, key);
    }
    @Test
    void privateAccountScopeReadStatePagingAndForeignIdentifiers() throws Exception {
        UUID other = createUser("other", "other@example.org", "Other member");
        String token = login("other", PASSWORD);
        publish(adminId, "invitation-a");
        publish(adminId, "invitation-b");
        publish(other, "private-c");
        assertThat(request("GET", ROOT, null, null).status()).isEqualTo(401);
        Response inbox = request("GET", ROOT + "?limit=1", admin, null);
        assertThat(inbox.status()).isEqualTo(200);
        assertThat(inbox.data()).containsEntry("unreadCount", 2).containsEntry("hasMore", true);
        assertThat(inbox.body()).doesNotContain("accountId", "sourceKey", "invitation-a", "private-c");
        String id = id(inbox);
        assertThat(request("PUT", ROOT + "/" + id + "/read", token, Map.of("read", true)).status()).isEqualTo(404);
        assertThat(request("PUT", ROOT + "/" + id + "/read", admin, Map.of("read", true)).status()).isEqualTo(204);
        OffsetDateTime firstRead = db.selectFrom(COMMUNICATION_MEMBER_NOTIFICATIONS)
            .where(COMMUNICATION_MEMBER_NOTIFICATIONS.ID.eq(UUID.fromString(id)))
            .fetchSingle()
            .getReadAt();

        assertThat(request("PUT", ROOT + "/" + id + "/read", admin, Map.of("read", true)).status()).isEqualTo(204);
        assertThat(db.selectFrom(COMMUNICATION_MEMBER_NOTIFICATIONS)
            .where(COMMUNICATION_MEMBER_NOTIFICATIONS.ID.eq(UUID.fromString(id)))
            .fetchSingle()
            .getReadAt()).isEqualTo(firstRead);

        assertThat(request("GET", ROOT + "?unreadOnly=true", admin, null).data()).containsEntry("unreadCount", 1);
        assertThat(request("PUT", ROOT + "/" + id + "/read", admin, Map.of("read", false)).status()).isEqualTo(204);
        assertThat(request("GET", ROOT, admin, null).data()).containsEntry("unreadCount", 2);
        assertThat(request("GET", ROOT + "?limit=0", admin, null).status()).isEqualTo(400);
        assertThat(request("GET", ROOT + "?offset=10001", admin, null).status()).isEqualTo(400);
        assertThat(request("PUT", ROOT + "/" + id + "/read", admin, Map.of()).status()).isEqualTo(400);
    }
    @SuppressWarnings("unchecked")
    private String id(Response response) {
        return (String) ((List<Map<String, Object>>) response.data().get("items")).getFirst().get("id");
    }
    @Test
    void preferencesAreVersionedPrivateAndOnlyAffectFutureInboxNotEmail() throws Exception {
        UUID other = createUser("other", "other@example.org", "Other member");
        String token = login("other", PASSWORD);
        assertThat(request("GET", PREFS, admin, null).body()).contains("VOLUNTEERING", "EVENTS", "\"enabled\":true",
            "\"version\":0");
        publish(adminId, "old");
        assertThat(
            request("PUT", PREFS + "/VOLUNTEERING", admin, Map.of("enabled", false, "expectedVersion", 0)).status())
            .isEqualTo(204);
        assertThat(
            request("PUT", PREFS + "/VOLUNTEERING", admin, Map.of("enabled", true, "expectedVersion", 0)).status())
            .isEqualTo(409);
        publish(adminId, "suppressed");
        assertThat(request("GET", ROOT, admin, null).data()).containsEntry("unreadCount", 1);
        assertThat(request("GET", PREFS, token, null).body()).contains("\"version\":0")
            .doesNotContain("\"enabled\":false");
        assertThat(request("PUT", PREFS + "/UNKNOWN", admin, Map.of("enabled", false, "expectedVersion", 0)).status())
            .isEqualTo(400);
        assertThat(
            request("PUT", PREFS + "/VOLUNTEERING", admin, Map.of("enabled", true, "expectedVersion", 1)).status())
            .isEqualTo(204);
        publish(adminId, "suppressed"); // A retry must not resurrect a previously suppressed notice.
        publish(adminId, "new");
        assertThat(request("GET", ROOT, admin, null).data()).containsEntry("unreadCount", 2);
    }
    @Test
    void retriesDoNotDuplicateAndOriginatingTransactionRollbackRemovesNotice() throws Exception {
        publish(adminId, "same");
        publish(adminId, "same");
        assertThatThrownBy(() -> tx.execute(() -> {
            publish(adminId, "rollback");
            throw new IllegalStateException("rollback");
        })).isInstanceOf(IllegalStateException.class);
        assertThat(request("GET", ROOT, admin, null).data()).containsEntry("unreadCount", 1);
        assertThat(db.fetchCount(COMMUNICATION_MEMBER_NOTIFICATIONS)).isEqualTo(1);
    }
    @Test
    void readsAreReadOnlyAndDoNotRequirePeopleOrFinancialBindings() throws Exception {
        UUID user = createUser("member", "member@example.org", "Member");
        String token = login("member", PASSWORD);
        assertThat(request("GET", ROOT, token, null).status()).isEqualTo(200);
        assertThat(request("GET", PREFS, token, null).status()).isEqualTo(200);
        assertThat(db.fetchCount(COMMUNICATION_MEMBER_NOTIFICATIONS)).isZero();
        assertThat(io.restassured.RestAssured.given()
            .baseUri("http://localhost")
            .port(port)
            .header("Authorization", "Bearer " + token)
            .get(ROOT)
            .header("Cache-Control")).contains("no-store");
    }
}
