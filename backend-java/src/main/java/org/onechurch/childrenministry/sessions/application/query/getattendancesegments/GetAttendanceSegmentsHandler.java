package org.onechurch.childrenministry.sessions.application.query.getattendancesegments;

import org.onechurch.childrenministry.sessions.application.contract.AttendanceReader;
import org.onechurch.childrenministry.sessions.domain.AttendanceSegment;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.util.List;

public final class GetAttendanceSegmentsHandler {
    private final PermissionChecker authorization;
    private final AttendanceReader attendance;
    public GetAttendanceSegmentsHandler(PermissionChecker authorization, AttendanceReader attendance) {
        this.authorization = authorization;
        this.attendance = attendance;
    }
    public List<AttendanceSegment> handle(GetAttendanceSegmentsQuery command) {
        authorization.requirePermission("children.read");
        return attendance.segments(command.attendanceId());
    }
}
