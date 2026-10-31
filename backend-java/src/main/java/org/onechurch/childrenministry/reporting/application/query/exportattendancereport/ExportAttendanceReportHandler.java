package org.onechurch.childrenministry.reporting.application.query.exportattendancereport;
import org.onechurch.childrenministry.reporting.application.AttendanceReport;
import org.onechurch.childrenministry.reporting.application.AttendanceReporting;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class ExportAttendanceReportHandler {
    private final PermissionChecker authorization;
    private final AttendanceReporting reporting;
    public ExportAttendanceReportHandler(PermissionChecker authorization, AttendanceReporting reporting) {
        this.authorization = authorization;
        this.reporting = reporting;
    }
    public AttendanceReport handle(ExportAttendanceReportQuery query) {
        authorization.requirePermission("children.read");
        authorization.requirePermission("children.manage");
        return reporting.export(query.filter());
    }
}
