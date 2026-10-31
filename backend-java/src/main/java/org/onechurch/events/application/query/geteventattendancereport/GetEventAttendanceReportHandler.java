package org.onechurch.events.application.query.geteventattendancereport;
import org.onechurch.events.application.EventReports;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class GetEventAttendanceReportHandler {
    private final PermissionChecker auth;
    private final EventReports reports;
    public GetEventAttendanceReportHandler(PermissionChecker auth, EventReports reports) {
        this.auth = auth;
        this.reports = reports;
    }
    public EventReports.Page handle(GetEventAttendanceReportQuery q) {
        auth.requirePermission("events.attendance.read");
        return reports.report(q.eventId(), q.search(), q.from(), q.until(), q.limit(), q.offset());
    }
}
