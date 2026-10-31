package org.onechurch.childrenministry.incidents.application.query.listincidentattendance;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.ChildrenIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class ListIncidentAttendanceHandlerIntegrationTest extends ChildrenIntegrationTestSupport {
    @Autowired private ListIncidentAttendanceHandler handler;
    @Test
    void directCallsRequireTrustedCurrentPermissions() {
        ListIncidentAttendanceQuery request = new ListIncidentAttendanceQuery(adminId, UUID.randomUUID(), "", 25, 0);
        assertIdentityFailure(() -> handler.handle(request), IdentityException.Kind.UNAUTHENTICATED);
        asActor(admin, () -> {
            db.deleteFrom(ROLE_PERMISSIONS)
                .where(ROLE_PERMISSIONS.PERMISSION_ID.in(db.select(PERMISSIONS.ID)
                    .from(PERMISSIONS)
                    .where(PERMISSIONS.CODE.in("children.incidents.manage", "children.incidents.record"))))
                .execute();
            assertIdentityFailure(() -> handler.handle(request), IdentityException.Kind.FORBIDDEN);
            return null;
        });
    }
}
