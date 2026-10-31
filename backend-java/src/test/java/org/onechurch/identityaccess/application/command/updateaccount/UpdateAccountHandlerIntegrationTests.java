package org.onechurch.identityaccess.application.command.updateaccount;

import static org.assertj.core.api.Assertions.assertThat;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.IdentityIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.Map;
import java.util.UUID;

class UpdateAccountHandlerIntegrationTests extends IdentityIntegrationTestSupport {

    @Autowired private UpdateAccountHandler handler;

    @Test
    void updatesTheAccountNameAndEmailAndSupportsNewEmailLogin() throws Exception {
        UUID id = createUser("solly", "solly@church.org", "Solly");
        assertThat(request("PUT", "/api/users/" + id, admin,
            Map.of("email", "solly.mabasa@church.org", "displayName", "Solly Mabasa")).status()).isEqualTo(204);
        String token = login("solly.mabasa@church.org", PASSWORD);
        assertThat(request("GET", "/api/me", token, null).data()).containsEntry("displayName", "Solly Mabasa");
        assertThat(
            request("POST", "/api/login_check", null, Map.of("username", "solly@church.org", "password", PASSWORD))
                .status())
            .isEqualTo(401);
    }

    @Test
    void directCallsRequireAuthenticationAndRejectMissingPermissionsAndActorSpoofing() throws Exception {
        UUID helperId = createUser("direct-helper", "direct-helper@church.org", "Direct Helper");
        String helper = login("direct-helper", PASSWORD);
        assertIdentityFailure(
            () -> handler.handle(new UpdateAccountCommand(adminId, helperId, "changed@church.org", "Changed Helper")),
            IdentityException.Kind.UNAUTHENTICATED);
        asActor(helper, () -> {
            assertIdentityFailure(
                () -> handler
                    .handle(new UpdateAccountCommand(adminId, helperId, "changed@church.org", "Changed Helper")),
                IdentityException.Kind.FORBIDDEN);
            assertIdentityFailure(
                () -> handler
                    .handle(new UpdateAccountCommand(helperId, helperId, "changed@church.org", "Changed Helper")),
                IdentityException.Kind.FORBIDDEN);
            return null;
        });
        asActor(admin, () -> {
            handler.handle(new UpdateAccountCommand(adminId, helperId, "changed@church.org", "Changed Helper"));
            return null;
        });
    }

}
