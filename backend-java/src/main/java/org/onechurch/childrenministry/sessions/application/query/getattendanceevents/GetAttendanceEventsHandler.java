package org.onechurch.childrenministry.sessions.application.query.getattendanceevents;

import org.onechurch.childrenministry.sessions.application.contract.AttendanceReader;
import org.onechurch.childrenministry.sessions.domain.AttendanceEvent;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.util.List;

public final class GetAttendanceEventsHandler {
    private final PermissionChecker authorization;
    private final AttendanceReader attendance;
    public GetAttendanceEventsHandler(PermissionChecker authorization, AttendanceReader attendance) {
        this.authorization = authorization;
        this.attendance = attendance;
    }
    public List<AttendanceEvent> handle(GetAttendanceEventsQuery command) {
        authorization.requirePermission("children.supervise");
        ChildrenRules.page(command.limit(), command.offset());
        return attendance.events(command.attendanceId(), command.limit(), command.offset());
    }
}
