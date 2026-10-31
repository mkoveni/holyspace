package org.onechurch.identityaccess.application.command.setaccountstatus;

import static org.assertj.core.api.Assertions.assertThat;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.IdentityIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.Map;
import java.util.UUID;

class SetAccountStatusHandlerIntegrationTests extends IdentityIntegrationTestSupport {

    @Autowired private SetAccountStatusHandler handler;

    @Test
    void disablingAnAccountRevokesSessionsAndEnablingDoesNotRestoreThem() throws Exception {
        UUID id = createUser("helper", "helper@church.org", "Helper");
        String helper = login("helper", PASSWORD);
        assertThat(request("POST", "/api/users/" + id + "/disable", admin, null).status()).isEqualTo(204);
        assertThat(request("GET", "/api/me", helper, null).status()).isEqualTo(401);
        assertThat(
            request("POST", "/api/login_check", null, Map.of("username", "helper", "password", PASSWORD)).status())
            .isEqualTo(401);
        assertThat(request("POST", "/api/users/" + id + "/enable", admin, null).status()).isEqualTo(204);
        assertThat(request("GET", "/api/me", helper, null).status()).isEqualTo(401);
        assertThat(login("helper", PASSWORD)).isNotBlank();
    }

    @Test
    void refusesToDisableTheLastManagerAndRollsBack() throws Exception {
        assertThat(request("POST", "/api/users/" + adminId + "/disable", admin, null).status()).isEqualTo(409);
        assertThat(request("GET", "/api/users", admin, null).status()).isEqualTo(200);
    }

    @Test
    void directCallsRequireAuthenticationAndRejectMissingPermissionsAndActorSpoofing() throws Exception {
        UUID helperId = createUser("direct-helper", "direct-helper@church.org", "Direct Helper");
        String helper = login("direct-helper", PASSWORD);
        assertIdentityFailure(() -> handler.handle(new SetAccountStatusCommand(adminId, helperId, false)),
            IdentityException.Kind.UNAUTHENTICATED);
        asActor(helper, () -> {
            assertIdentityFailure(() -> handler.handle(new SetAccountStatusCommand(adminId, helperId, false)),
                IdentityException.Kind.FORBIDDEN);
            assertIdentityFailure(() -> handler.handle(new SetAccountStatusCommand(helperId, helperId, false)),
                IdentityException.Kind.FORBIDDEN);
            return null;
        });
        asActor(admin, () -> {
            handler.handle(new SetAccountStatusCommand(adminId, helperId, false));
            return null;
        });
    }

}
