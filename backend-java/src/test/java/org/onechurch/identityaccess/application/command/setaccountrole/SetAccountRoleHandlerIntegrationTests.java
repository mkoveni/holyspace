package org.onechurch.identityaccess.application.command.setaccountrole;

import static org.assertj.core.api.Assertions.assertThat;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.IdentityIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;

class SetAccountRoleHandlerIntegrationTests extends IdentityIntegrationTestSupport {

    @Autowired private SetAccountRoleHandler handler;

    @Test
    void refusesToRemoveTheLastManagerRoleAndRollsBack() throws Exception {
        assertThat(request("DELETE", "/api/users/" + adminId + "/roles/" + ADMIN_ROLE, admin, null).status())
            .isEqualTo(409);
        assertThat(request("GET", "/api/users", admin, null).status()).isEqualTo(200);
    }

    @Test
    void directCallsRequireAuthenticationAndRejectMissingPermissionsAndActorSpoofing() throws Exception {
        UUID helperId = createUser("direct-helper", "direct-helper@church.org", "Direct Helper");
        String helper = login("direct-helper", PASSWORD);
        assertIdentityFailure(() -> handler.handle(new SetAccountRoleCommand(adminId, helperId, ADMIN_ROLE, true)),
            IdentityException.Kind.UNAUTHENTICATED);
        asActor(helper, () -> {
            assertIdentityFailure(() -> handler.handle(new SetAccountRoleCommand(adminId, helperId, ADMIN_ROLE, true)),
                IdentityException.Kind.FORBIDDEN);
            assertIdentityFailure(() -> handler.handle(new SetAccountRoleCommand(helperId, helperId, ADMIN_ROLE, true)),
                IdentityException.Kind.FORBIDDEN);
            return null;
        });
        asActor(admin, () -> {
            handler.handle(new SetAccountRoleCommand(adminId, helperId, ADMIN_ROLE, true));
            return null;
        });
    }

}
