package org.onechurch.identityaccess.application.authorization;

import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.ACCOUNT_SESSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.USER_ACCOUNTS;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.IdentityIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.time.OffsetDateTime;
import java.util.UUID;

class AuthorizationIntegrationTests extends IdentityIntegrationTestSupport {
    @Autowired private Authorization authorization;

    @Test
    void permissionRevocationIsObservedEvenWithAnExistingAuthenticationContext() throws Exception {
        UUID helperId = createUser("coordinator", "coordinator@church.org", "Coordinator");
        assertThat(request("POST", "/api/users/" + helperId + "/roles/" + ADMIN_ROLE, admin, null).status())
            .isEqualTo(204);
        String helper = login("coordinator", PASSWORD);
        asActor(helper, () -> {
            authorization.requirePermission("identity.manage");
            authorization.requirePermission("identity.read");
            try {
                assertThat(request("DELETE", "/api/users/" + helperId + "/roles/" + ADMIN_ROLE, admin, null).status())
                    .isEqualTo(204);
            } catch (Exception error) {
                throw new IllegalStateException(error);
            }
            assertIdentityFailure(() -> authorization.requirePermission("identity.manage"),
                IdentityException.Kind.FORBIDDEN);
            return null;
        });
    }

    @Test
    void deletedAndExpiredSessionsCannotAuthorizeDirectCalls() {
        asActor(admin, () -> {
            db.update(ACCOUNT_SESSIONS)
                .set(ACCOUNT_SESSIONS.EXPIRES_AT, OffsetDateTime.now().minusSeconds(1))
                .where(ACCOUNT_SESSIONS.TOKEN_HASH.eq(tokens.hash(admin)))
                .execute();
            assertIdentityFailure(() -> authorization.requirePermission("identity.manage"),
                IdentityException.Kind.UNAUTHENTICATED);
            db.deleteFrom(ACCOUNT_SESSIONS).where(ACCOUNT_SESSIONS.TOKEN_HASH.eq(tokens.hash(admin))).execute();
            assertIdentityFailure(() -> authorization.requirePermission("identity.manage"),
                IdentityException.Kind.UNAUTHENTICATED);
            return null;
        });
    }

    @Test
    void disabledAccountsCannotAuthorizeDirectCallsEvenWithAnExistingSession() {
        asActor(admin, () -> {
            db.update(USER_ACCOUNTS)
                .set(USER_ACCOUNTS.STATUS, "disabled")
                .where(USER_ACCOUNTS.ID.eq(adminId))
                .execute();
            assertIdentityFailure(() -> authorization.requirePermission("identity.manage"),
                IdentityException.Kind.UNAUTHENTICATED);
            return null;
        });
    }
}
