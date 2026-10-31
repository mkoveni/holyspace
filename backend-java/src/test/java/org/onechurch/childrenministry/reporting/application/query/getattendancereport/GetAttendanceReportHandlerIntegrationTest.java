package org.onechurch.childrenministry.reporting.application.query.getattendancereport;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.childrenministry.reporting.application.AttendanceReportFilter;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.ChildrenIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class GetAttendanceReportHandlerIntegrationTest extends ChildrenIntegrationTestSupport {
    @Autowired private GetAttendanceReportHandler handler;
    @Test
    void directCallsRequireAnAuthenticatedCallerAndLivePermission() {
        GetAttendanceReportQuery query =
            new GetAttendanceReportQuery(new AttendanceReportFilter(today, today, null, null, null, "", "ALL"), 25, 0);
        assertIdentityFailure(() -> handler.handle(query), IdentityException.Kind.UNAUTHENTICATED);
        asActor(admin, () -> {
            java.util.List<UUID> permissions = db.select(PERMISSIONS.ID)
                .from(PERMISSIONS)
                .where(PERMISSIONS.CODE.in("children.read", "children.manage"))
                .fetch(PERMISSIONS.ID);
            db.deleteFrom(ROLE_PERMISSIONS).where(ROLE_PERMISSIONS.PERMISSION_ID.in(permissions)).execute();
            assertIdentityFailure(() -> handler.handle(query), IdentityException.Kind.FORBIDDEN);
            return null;
        });
    }
}
