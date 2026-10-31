package org.onechurch.childrenministry.incidents.application.command.reportincident;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.ChildrenIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class ReportIncidentHandlerIntegrationTest extends ChildrenIntegrationTestSupport {
    @Autowired private ReportIncidentHandler handler;
    @Test
    void directCallsRequireTrustedCurrentPermissions() {
        ReportIncidentCommand request =
            new ReportIncidentCommand(adminId, null, null, null, null, null, null, null, null, null, null, null);
        assertIdentityFailure(() -> handler.handle(request), IdentityException.Kind.UNAUTHENTICATED);
        asActor(admin, () -> {
            assertIdentityFailure(() -> handler.handle(new ReportIncidentCommand(UUID.randomUUID(), null, null, null,
                null, null, null, null, null, null, null, null)), IdentityException.Kind.FORBIDDEN);
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
