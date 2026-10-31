package org.onechurch.childrenministry.sessions.application.query.listplanningworkers;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.ChildrenIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class ListPlanningWorkersHandlerIntegrationTest extends ChildrenIntegrationTestSupport {
    @Autowired private ListPlanningWorkersHandler handler;
    @Test
    void directCallsRequireTrustedCurrentPermissions() {
        ListPlanningWorkersQuery request = new ListPlanningWorkersQuery("", 25, 0);
        assertIdentityFailure(() -> handler.handle(request), IdentityException.Kind.UNAUTHENTICATED);
        asActor(admin, () -> {
            UUID revoked = db.select(PERMISSIONS.ID)
                .from(PERMISSIONS)
                .where(PERMISSIONS.CODE.eq("children.manage"))
                .fetchOne(PERMISSIONS.ID);
            db.deleteFrom(ROLE_PERMISSIONS).where(ROLE_PERMISSIONS.PERMISSION_ID.eq(revoked)).execute();
            assertIdentityFailure(() -> handler.handle(request), IdentityException.Kind.FORBIDDEN);
            return null;
        });
    }
}
