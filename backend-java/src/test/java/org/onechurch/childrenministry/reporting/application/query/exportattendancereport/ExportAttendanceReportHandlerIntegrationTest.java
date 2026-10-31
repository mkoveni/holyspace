package org.onechurch.childrenministry.reporting.application.query.exportattendancereport;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.childrenministry.reporting.application.AttendanceReportFilter;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.ChildrenIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class ExportAttendanceReportHandlerIntegrationTest extends ChildrenIntegrationTestSupport {
    @Autowired private ExportAttendanceReportHandler handler;
    @Test
    void directCallsRequireAnAuthenticatedCallerAndLivePermission() {
        ExportAttendanceReportQuery query =
            new ExportAttendanceReportQuery(new AttendanceReportFilter(today, today, null, null, null, "", "ALL"));
        assertIdentityFailure(() -> handler.handle(query), IdentityException.Kind.UNAUTHENTICATED);
        asActor(admin, () -> {
            UUID permission = db.select(PERMISSIONS.ID)
                .from(PERMISSIONS)
                .where(PERMISSIONS.CODE.eq("children.manage"))
                .fetchOne(PERMISSIONS.ID);
            db.deleteFrom(ROLE_PERMISSIONS).where(ROLE_PERMISSIONS.PERMISSION_ID.eq(permission)).execute();
            assertIdentityFailure(() -> handler.handle(query), IdentityException.Kind.FORBIDDEN);
            return null;
        });
    }
}
