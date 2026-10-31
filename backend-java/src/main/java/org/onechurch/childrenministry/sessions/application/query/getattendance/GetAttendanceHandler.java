package org.onechurch.childrenministry.sessions.application.query.getattendance;

import org.onechurch.childrenministry.sessions.application.AttendanceView;
import org.onechurch.childrenministry.sessions.application.StaffScope;
import org.onechurch.childrenministry.sessions.application.contract.AttendanceReader;
import org.onechurch.childrenministry.sessions.domain.Attendance;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class GetAttendanceHandler {
    private final PermissionChecker authorization;
    private final AttendanceReader attendance;
    private final StaffScope scope;
    public GetAttendanceHandler(PermissionChecker authorization, AttendanceReader attendance, StaffScope scope) {
        this.authorization = authorization;
        this.attendance = attendance;
        this.scope = scope;
    }
    public AttendanceView handle(GetAttendanceQuery query) {
        authorization.requirePermission(query.actorId(), "children.station");
        Attendance value =
            attendance.attendance(query.attendanceId()).orElseThrow(() -> ChildrenException.missing("Attendance"));
        scope.require(query.actorId(), value.sessionId());
        return AttendanceView.from(value);
    }
}
