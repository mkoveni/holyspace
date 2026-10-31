package org.onechurch.childrenministry.incidents.application.command.purgeexpiredincidents;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.ChildrenIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class PurgeExpiredIncidentsHandlerIntegrationTest extends ChildrenIntegrationTestSupport {
    @Autowired private PurgeExpiredIncidentsHandler handler;
    @Test
    void directCallsRequireTrustedCurrentPermissions() {
        PurgeExpiredIncidentsCommand request = new PurgeExpiredIncidentsCommand(adminId, 100);
        assertIdentityFailure(() -> handler.handle(request), IdentityException.Kind.UNAUTHENTICATED);
        asActor(admin, () -> {
            assertIdentityFailure(() -> handler.handle(new PurgeExpiredIncidentsCommand(UUID.randomUUID(), 100)),
                IdentityException.Kind.FORBIDDEN);
            db.deleteFrom(ROLE_PERMISSIONS)
                .where(ROLE_PERMISSIONS.PERMISSION_ID.in(db.select(PERMISSIONS.ID)
                    .from(PERMISSIONS)
                    .where(PERMISSIONS.CODE.in("children.incidents.manage"))))
                .execute();
            assertIdentityFailure(() -> handler.handle(request), IdentityException.Kind.FORBIDDEN);
            return null;
        });
    }
}
