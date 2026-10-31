package org.onechurch.childrenministry.messaging.application.command.createguardiancommunication;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.ChildrenIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class CreateGuardianCommunicationHandlerIntegrationTest extends ChildrenIntegrationTestSupport {
    @Autowired private CreateGuardianCommunicationHandler handler;
    @Test
    void directCallsRequireTrustedCurrentPermissions() {
        CreateGuardianCommunicationCommand request =
            new CreateGuardianCommunicationCommand(adminId, null, null, null, null, null, null, null);
        assertIdentityFailure(() -> handler.handle(request), IdentityException.Kind.UNAUTHENTICATED);
        asActor(admin, () -> {
            assertIdentityFailure(() -> handler.handle(
                new CreateGuardianCommunicationCommand(UUID.randomUUID(), null, null, null, null, null, null, null)),
                IdentityException.Kind.FORBIDDEN);
            db.deleteFrom(ROLE_PERMISSIONS)
                .where(ROLE_PERMISSIONS.PERMISSION_ID.in(
                    db.select(PERMISSIONS.ID).from(PERMISSIONS).where(PERMISSIONS.CODE.in("children.safeguarding"))))
                .execute();
            assertIdentityFailure(() -> handler.handle(request), IdentityException.Kind.FORBIDDEN);
            return null;
        });
    }
}
