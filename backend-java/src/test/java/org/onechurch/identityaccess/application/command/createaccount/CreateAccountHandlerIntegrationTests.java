package org.onechurch.identityaccess.application.command.createaccount;

import static org.assertj.core.api.Assertions.assertThat;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.IdentityIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.Map;
import java.util.UUID;

class CreateAccountHandlerIntegrationTests extends IdentityIntegrationTestSupport {

    @Autowired private CreateAccountHandler handler;

    @Test
    void requiresNamesAndEmailAndEnforcesCaseInsensitiveUniqueness() throws Exception {
        assertThat(request("POST", "/api/users", admin, Map.of("username", "missing", "password", PASSWORD)).status())
            .isEqualTo(400);
        assertThat(request("POST", "/api/users", admin,
            Map.of("username", "invalid", "email", "not-email", "displayName", "Invalid", "password", PASSWORD))
            .status()).isEqualTo(400);
        assertThat(request("POST", "/api/users", admin,
            Map.of("username", "blank", "email", "blank@church.org", "displayName", "  ", "password", PASSWORD))
            .status()).isEqualTo(400);
        createUser("solly", "solly@church.org", "Solly Mabasa");
        assertThat(request("POST", "/api/users", admin,
            Map.of("username", "other", "email", "SOLLY@church.org", "displayName", "Other", "password", PASSWORD))
            .status()).isEqualTo(409);
        assertThat(request("POST", "/api/users", admin,
            Map.of("username", "SOLLY", "email", "different@church.org", "displayName", "Other", "password", PASSWORD))
            .status()).isEqualTo(409);
        assertThat(request("GET", "/api/users?search=mabasa", admin, null).body())
            .contains("Solly Mabasa", "solly@church.org")
            .doesNotContain("passwordHash");
    }

    @Test
    void directCallsRequireAuthenticationAndRejectMissingPermissionsAndActorSpoofing() throws Exception {
        UUID helperId = createUser("direct-helper", "direct-helper@church.org", "Direct Helper");
        String helper = login("direct-helper", PASSWORD);
        assertIdentityFailure(
            () -> handler
                .handle(new CreateAccountCommand(adminId, "direct-user", "direct@church.org", "Direct User", PASSWORD)),
            IdentityException.Kind.UNAUTHENTICATED);
        asActor(helper, () -> {
            assertIdentityFailure(
                () -> handler.handle(
                    new CreateAccountCommand(adminId, "direct-user", "direct@church.org", "Direct User", PASSWORD)),
                IdentityException.Kind.FORBIDDEN);
            assertIdentityFailure(
                () -> handler.handle(
                    new CreateAccountCommand(helperId, "direct-user", "direct@church.org", "Direct User", PASSWORD)),
                IdentityException.Kind.FORBIDDEN);
            return null;
        });
        asActor(admin, () -> {
            handler
                .handle(new CreateAccountCommand(adminId, "direct-user", "direct@church.org", "Direct User", PASSWORD));
            return null;
        });
    }

}
