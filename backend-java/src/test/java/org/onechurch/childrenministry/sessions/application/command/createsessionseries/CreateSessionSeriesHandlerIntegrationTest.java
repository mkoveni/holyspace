package org.onechurch.childrenministry.sessions.application.command.createsessionseries;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.ChildrenIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.List;
import java.util.UUID;
class CreateSessionSeriesHandlerIntegrationTest extends ChildrenIntegrationTestSupport {
    @Autowired private CreateSessionSeriesHandler handler;
    @Test
    void directCallsRequireTrustedCurrentPermissions() {
        CreateSessionSeriesCommand request = new CreateSessionSeriesCommand(adminId, UUID.randomUUID(), null);
        assertIdentityFailure(() -> handler.handle(request), IdentityException.Kind.UNAUTHENTICATED);
        asActor(admin, () -> {
            assertIdentityFailure(
                () -> handler.handle(new CreateSessionSeriesCommand(UUID.randomUUID(), UUID.randomUUID(), null)),
                IdentityException.Kind.FORBIDDEN);
            List<UUID> revoked = db.select(PERMISSIONS.ID)
                .from(PERMISSIONS)
                .where(PERMISSIONS.CODE.in("children.manage"))
                .fetch(PERMISSIONS.ID);
            db.deleteFrom(ROLE_PERMISSIONS).where(ROLE_PERMISSIONS.PERMISSION_ID.in(revoked)).execute();
            assertIdentityFailure(() -> handler.handle(request), IdentityException.Kind.FORBIDDEN);
            return null;
        });
    }
}
