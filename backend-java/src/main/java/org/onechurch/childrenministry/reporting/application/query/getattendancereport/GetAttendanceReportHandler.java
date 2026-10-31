package org.onechurch.childrenministry.reporting.application.query.getattendancereport;
import org.onechurch.childrenministry.reporting.application.AttendanceReport;
import org.onechurch.childrenministry.reporting.application.AttendanceReporting;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class GetAttendanceReportHandler {
    private final PermissionChecker authorization;
    private final AttendanceReporting reporting;
    public GetAttendanceReportHandler(PermissionChecker authorization, AttendanceReporting reporting) {
        this.authorization = authorization;
        this.reporting = reporting;
    }
    public AttendanceReport handle(GetAttendanceReportQuery query) {
        authorization.requirePermission("children.read");

        return reporting.report(query.filter(), query.limit(), query.offset());
    }
}
