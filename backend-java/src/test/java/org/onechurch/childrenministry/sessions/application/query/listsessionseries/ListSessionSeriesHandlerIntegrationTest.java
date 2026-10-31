package org.onechurch.childrenministry.sessions.application.query.listsessionseries;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.ChildrenIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.List;
import java.util.UUID;
class ListSessionSeriesHandlerIntegrationTest extends ChildrenIntegrationTestSupport {
    @Autowired private ListSessionSeriesHandler handler;
    @Test
    void directCallsRequireTrustedCurrentPermissions() {
        ListSessionSeriesQuery request = new ListSessionSeriesQuery(25, 0);
        assertIdentityFailure(() -> handler.handle(request), IdentityException.Kind.UNAUTHENTICATED);
        asActor(admin, () -> {

            List<UUID> revoked = db.select(PERMISSIONS.ID)
                .from(PERMISSIONS)
                .where(PERMISSIONS.CODE.in("children.read", "children.manage"))
                .fetch(PERMISSIONS.ID);
            db.deleteFrom(ROLE_PERMISSIONS).where(ROLE_PERMISSIONS.PERMISSION_ID.in(revoked)).execute();
            assertIdentityFailure(() -> handler.handle(request), IdentityException.Kind.FORBIDDEN);
            return null;
        });
    }
}
