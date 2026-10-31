package org.onechurch.childrenministry.reporting.application.query.getattendancetimeline;
import org.onechurch.childrenministry.reporting.application.AttendanceReport;
import org.onechurch.childrenministry.reporting.application.AttendanceReporting;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class GetAttendanceTimelineHandler {
    private final PermissionChecker authorization;
    private final AttendanceReporting reporting;
    public GetAttendanceTimelineHandler(PermissionChecker authorization, AttendanceReporting reporting) {
        this.authorization = authorization;
        this.reporting = reporting;
    }
    public AttendanceReport handle(GetAttendanceTimelineQuery query) {
        authorization.requirePermission("children.read");

        return reporting.timeline(query.attendanceId(), query.limit(), query.offset());
    }
}
