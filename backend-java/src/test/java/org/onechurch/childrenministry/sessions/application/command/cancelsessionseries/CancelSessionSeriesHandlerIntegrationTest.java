package org.onechurch.childrenministry.sessions.application.command.cancelsessionseries;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.ChildrenIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.List;
import java.util.UUID;
class CancelSessionSeriesHandlerIntegrationTest extends ChildrenIntegrationTestSupport {
    @Autowired private CancelSessionSeriesHandler handler;
    @Test
    void directCallsRequireTrustedCurrentPermissions() {
        CancelSessionSeriesCommand request =
            new CancelSessionSeriesCommand(adminId, UUID.randomUUID(), 0, "Term change");
        assertIdentityFailure(() -> handler.handle(request), IdentityException.Kind.UNAUTHENTICATED);
        asActor(admin, () -> {
            assertIdentityFailure(
                () -> handler.handle(new CancelSessionSeriesCommand(UUID.randomUUID(), UUID.randomUUID(), 0, "Change")),
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
