package org.onechurch.events.application.query.geteventattendancehistory;
import org.onechurch.events.application.AttendanceViews;
import org.onechurch.events.application.contract.AttendanceReader;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.util.List;
public final class GetEventAttendanceHistoryHandler {
    private final PermissionChecker auth;
    private final AttendanceViews views;
    public GetEventAttendanceHistoryHandler(PermissionChecker auth, AttendanceViews views) {
        this.auth = auth;
        this.views = views;
    }
    public List<AttendanceReader.History> handle(GetEventAttendanceHistoryQuery q) {
        auth.requirePermission("events.attendance.read");
        return views.history(q.registrationId(), q.limit(), q.offset());
    }
}
