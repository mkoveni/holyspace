package org.onechurch.identityaccess.application.command.changepassword;

import static org.assertj.core.api.Assertions.assertThat;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.IdentityIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.Map;
import java.util.UUID;

class ChangePasswordHandlerIntegrationTests extends IdentityIntegrationTestSupport {

    @Autowired private ChangePasswordHandler handler;

    @Test
    void changingPasswordRequiresTheCurrentPasswordAndRevokesAllSessions() throws Exception {
        UUID id = createUser("helper", "helper@church.org", "Helper");
        String first = login("helper", PASSWORD);
        String second = login("helper", PASSWORD);
        assertThat(request("PUT", "/api/me/password", first,
            Map.of("currentPassword", "wrong", "password", "Another-strong-password")).status()).isEqualTo(403);
        assertThat(request("PUT", "/api/me/password", first,
            Map.of("currentPassword", PASSWORD, "password", "Another-strong-password")).status()).isEqualTo(204);
        assertThat(request("GET", "/api/me", first, null).status()).isEqualTo(401);
        assertThat(request("GET", "/api/me", second, null).status()).isEqualTo(401);
        assertThat(login("helper", "Another-strong-password")).isNotBlank();
        assertThat(request("PUT", "/api/users/" + id + "/password", admin, Map.of("password", "Admin-reset-password"))
            .status()).isEqualTo(204);
        assertThat(login("helper", "Admin-reset-password")).isNotBlank();
    }

    @Test
    void directCallsCannotSpoofAnAdministratorOrResetAnotherAccountsPassword() throws Exception {
        UUID helperId = createUser("password-helper", "password-helper@church.org", "Password Helper");
        String helper = login("password-helper", PASSWORD);
        assertIdentityFailure(
            () -> handler.handle(new ChangePasswordCommand(adminId, helperId, null, "A-new-password-2026", true)),
            IdentityException.Kind.UNAUTHENTICATED);
        asActor(helper, () -> {
            assertIdentityFailure(
                () -> handler.handle(new ChangePasswordCommand(adminId, helperId, null, "A-new-password-2026", true)),
                IdentityException.Kind.FORBIDDEN);
            assertIdentityFailure(
                () -> handler.handle(new ChangePasswordCommand(helperId, helperId, null, "A-new-password-2026", true)),
                IdentityException.Kind.FORBIDDEN);
            assertIdentityFailure(
                () -> handler
                    .handle(new ChangePasswordCommand(helperId, adminId, PASSWORD, "A-new-password-2026", false)),
                IdentityException.Kind.FORBIDDEN);
            handler.handle(new ChangePasswordCommand(helperId, helperId, PASSWORD, "A-new-password-2026", false));
            return null;
        });
        assertThat(login("password-helper", "A-new-password-2026")).isNotBlank();
        assertThat(request("GET", "/api/me", helper, null).status()).isEqualTo(401);
    }

}
