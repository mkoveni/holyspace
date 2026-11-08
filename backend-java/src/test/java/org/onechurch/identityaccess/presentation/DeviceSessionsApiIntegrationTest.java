package org.onechurch.identityaccess.presentation;
import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.ACCOUNT_SESSIONS;

import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.IdentityIntegrationTestSupport;

import java.util.Map;
import java.util.UUID;
class DeviceSessionsApiIntegrationTest extends IdentityIntegrationTestSupport {
    private UUID account;
    private String mobile, other;
    @BeforeEach
    void setupSessions() throws Exception {
        account = createUser("member", "member@example.org", "Member");
        Response login = request("POST", "/api/login_check", null,
            Map.of("username", "member", "password", PASSWORD, "deviceName", "Divine Community Space · Android"));
        assertThat(login.status()).isEqualTo(200);
        mobile = (String) login.data().get("token");
        other = login("member", PASSWORD);
    }
    @Test
    void sessionsAreScopedRedactedAndCanBeRevokedWithoutStaffPermissions() throws Exception {
        Response listed = request("GET", "/api/me/sessions", mobile, null);
        assertThat(listed.status()).isEqualTo(200);
        assertThat(listed.body()).contains("Divine Community Space · Android", "Web browser", "\"current\":true")
            .doesNotContain("tokenHash", mobile, other);
        UUID otherSession = db.select(ACCOUNT_SESSIONS.ID)
            .from(ACCOUNT_SESSIONS)
            .where(ACCOUNT_SESSIONS.TOKEN_HASH.eq(tokens.hash(other)))
            .fetchOne(ACCOUNT_SESSIONS.ID);
        assertThat(request("DELETE", "/api/me/sessions/" + otherSession, mobile, null).status()).isEqualTo(204);
        assertThat(request("GET", "/api/me", other, null).status()).isEqualTo(401);
        assertThat(request("GET", "/api/me", mobile, null).status()).isEqualTo(200);
        UUID adminSession = db.select(ACCOUNT_SESSIONS.ID)
            .from(ACCOUNT_SESSIONS)
            .where(ACCOUNT_SESSIONS.TOKEN_HASH.eq(tokens.hash(admin)))
            .fetchOne(ACCOUNT_SESSIONS.ID);
        assertThat(request("DELETE", "/api/me/sessions/" + adminSession, mobile, null).status()).isEqualTo(404);
    }
    @Test
    void currentSessionAndPasswordChangeImmediatelyRevokeAccess() throws Exception {
        UUID id = db.select(ACCOUNT_SESSIONS.ID)
            .from(ACCOUNT_SESSIONS)
            .where(ACCOUNT_SESSIONS.TOKEN_HASH.eq(tokens.hash(mobile)))
            .fetchOne(ACCOUNT_SESSIONS.ID);
        assertThat(request("DELETE", "/api/me/sessions/" + id, mobile, null).status()).isEqualTo(204);
        assertThat(request("GET", "/api/me/sessions", mobile, null).status()).isEqualTo(401);
        assertThat(request("PUT", "/api/me/password", other,
            Map.of("currentPassword", PASSWORD, "password", "A-new-password-2026")).status()).isEqualTo(204);
        assertThat(request("GET", "/api/me", other, null).status()).isEqualTo(401);
    }
}
