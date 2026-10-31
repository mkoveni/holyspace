package org.onechurch.identityaccess.application.command.login;

import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.ACCOUNT_SESSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.LOGIN_ATTEMPTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.USER_ACCOUNTS;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.IdentityIntegrationTestSupport;

import java.time.OffsetDateTime;
import java.util.List;
import java.util.Map;

class LoginHandlerIntegrationTests extends IdentityIntegrationTestSupport {

    @Test
    void signsInByUsernameOrEmailAndNeverExposesCredentials() throws Exception {
        String emailToken = login("ADMIN@CHURCH.ORG", PASSWORD);
        var me = request("GET", "/api/me", emailToken, null);
        assertThat(me.status()).isEqualTo(200);
        assertThat(me.data()).containsEntry("username", "admin")
            .containsEntry("email", "admin@church.org")
            .containsEntry("displayName", "Church Administrator");
        assertThat(me.body()).doesNotContain("password", "token_hash");
        assertThat((List<String>) me.data().get("permissions")).contains("identity.manage", "identity.read");
        String stored = db.select(USER_ACCOUNTS.PASSWORD_HASH)
            .from(USER_ACCOUNTS)
            .where(USER_ACCOUNTS.ID.eq(adminId))
            .fetchSingle()
            .value1();
        assertThat(stored).startsWith("$2").isNotEqualTo(PASSWORD);
        assertThat(db.fetchCount(ACCOUNT_SESSIONS, ACCOUNT_SESSIONS.TOKEN_HASH.eq(tokens.hash(emailToken))))
            .isEqualTo(1);
        assertThat(db.fetchCount(ACCOUNT_SESSIONS, ACCOUNT_SESSIONS.TOKEN_HASH.eq(emailToken))).isZero();
    }

    @Test
    void aWrongPasswordRecordsFailureAndSuccessfulLoginClearsIt() throws Exception {
        assertThat(
            request("POST", "/api/login_check", null, Map.of("username", "admin", "password", "incorrect-password"))
                .status())
            .isEqualTo(401);
        assertThat(db.fetchCount(LOGIN_ATTEMPTS, LOGIN_ATTEMPTS.IDENTIFIER_HASH.eq(tokens.hash("admin")))).isEqualTo(1);
        assertThat(db.fetchCount(ACCOUNT_SESSIONS)).isEqualTo(1);

        String token = login("admin", PASSWORD);

        assertThat(request("GET", "/api/me", token, null).status()).isEqualTo(200);
        assertThat(db.fetchCount(LOGIN_ATTEMPTS, LOGIN_ATTEMPTS.IDENTIFIER_HASH.eq(tokens.hash("admin")))).isZero();
        assertThat(db.fetchCount(ACCOUNT_SESSIONS)).isEqualTo(2);
    }

    @Test
    void invalidCredentialsAreGenericAndLoginIsRateLimited() throws Exception {
        for (int i = 0; i < 5; i++) {
            var response =
                request("POST", "/api/login_check", null, Map.of("username", "unknown", "password", PASSWORD));
            assertThat(response.status()).isEqualTo(401);
            assertThat(response.data()).containsEntry("message", "Invalid username or password.");
        }
        assertThat(
            request("POST", "/api/login_check", null, Map.of("username", "unknown", "password", PASSWORD)).status())
            .isEqualTo(429);
        db.update(LOGIN_ATTEMPTS).set(LOGIN_ATTEMPTS.RESETS_AT, OffsetDateTime.now().minusMinutes(2)).execute();
        assertThat(
            request("POST", "/api/login_check", null, Map.of("username", "unknown", "password", PASSWORD)).status())
            .isEqualTo(401);
    }
}
