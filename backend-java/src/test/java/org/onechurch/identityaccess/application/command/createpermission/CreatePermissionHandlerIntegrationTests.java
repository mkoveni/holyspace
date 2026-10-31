package org.onechurch.identityaccess.application.command.createpermission;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.IdentityIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;

class CreatePermissionHandlerIntegrationTests extends IdentityIntegrationTestSupport {

    @Autowired private CreatePermissionHandler handler;

    @Test
    void directCallsRequireAuthenticationAndRejectMissingPermissionsAndActorSpoofing() throws Exception {
        UUID helperId = createUser("direct-helper", "direct-helper@church.org", "Direct Helper");
        String helper = login("direct-helper", PASSWORD);
        assertIdentityFailure(
            () -> handler.handle(new CreatePermissionCommand(adminId, "direct.test", "Direct permission")),
            IdentityException.Kind.UNAUTHENTICATED);
        asActor(helper, () -> {
            assertIdentityFailure(
                () -> handler.handle(new CreatePermissionCommand(adminId, "direct.test", "Direct permission")),
                IdentityException.Kind.FORBIDDEN);
            assertIdentityFailure(
                () -> handler.handle(new CreatePermissionCommand(helperId, "direct.test", "Direct permission")),
                IdentityException.Kind.FORBIDDEN);
            return null;
        });
        asActor(admin, () -> {
            handler.handle(new CreatePermissionCommand(adminId, "direct.test", "Direct permission"));
            return null;
        });
    }

}
