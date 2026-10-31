package org.onechurch.identityaccess.application.command.createrole;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.IdentityIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;

class CreateRoleHandlerIntegrationTests extends IdentityIntegrationTestSupport {

    @Autowired private CreateRoleHandler handler;

    @Test
    void directCallsRequireAuthenticationAndRejectMissingPermissionsAndActorSpoofing() throws Exception {
        UUID helperId = createUser("direct-helper", "direct-helper@church.org", "Direct Helper");
        String helper = login("direct-helper", PASSWORD);
        assertIdentityFailure(() -> handler.handle(new CreateRoleCommand(adminId, "direct-role", "Direct role")),
            IdentityException.Kind.UNAUTHENTICATED);
        asActor(helper, () -> {
            assertIdentityFailure(() -> handler.handle(new CreateRoleCommand(adminId, "direct-role", "Direct role")),
                IdentityException.Kind.FORBIDDEN);
            assertIdentityFailure(() -> handler.handle(new CreateRoleCommand(helperId, "direct-role", "Direct role")),
                IdentityException.Kind.FORBIDDEN);
            return null;
        });
        asActor(admin, () -> {
            handler.handle(new CreateRoleCommand(adminId, "direct-role", "Direct role"));
            return null;
        });
    }

}
