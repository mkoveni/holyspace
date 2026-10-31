package org.onechurch.events.application.query.listeventattendance;
import org.onechurch.events.application.AttendanceViews;
import org.onechurch.events.application.contract.AttendanceReader;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class ListEventAttendanceHandler {
    private final PermissionChecker auth;
    private final AttendanceViews views;
    public ListEventAttendanceHandler(PermissionChecker auth, AttendanceViews views) {
        this.auth = auth;
        this.views = views;
    }
    public AttendanceReader.Page handle(ListEventAttendanceQuery q) {
        auth.requirePermission("events.attendance.read");
        return views.roster(q.occurrenceId(), q.search(), q.status(), q.limit(), q.offset());
    }
}
