package org.onechurch.events.application.query.exporteventattendancereport;
import org.onechurch.events.application.EventReports;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class ExportEventAttendanceReportHandler {
    private final PermissionChecker auth;
    private final EventReports reports;
    public ExportEventAttendanceReportHandler(PermissionChecker auth, EventReports reports) {
        this.auth = auth;
        this.reports = reports;
    }
    public String handle(ExportEventAttendanceReportQuery q) {
        auth.requirePermission("events.attendance.read");
        return reports.export(q.eventId(), q.search(), q.from(), q.until());
    }
}
