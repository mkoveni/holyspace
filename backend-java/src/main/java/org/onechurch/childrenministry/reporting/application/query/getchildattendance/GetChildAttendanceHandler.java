package org.onechurch.childrenministry.reporting.application.query.getchildattendance;

import org.onechurch.childrenministry.sessions.application.AttendanceView;
import org.onechurch.childrenministry.sessions.application.contract.AttendanceReader;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.util.List;
public final class GetChildAttendanceHandler {
    private final PermissionChecker authorization;
    private final AttendanceReader attendance;
    public GetChildAttendanceHandler(PermissionChecker authorization, AttendanceReader attendance) {
        this.authorization = authorization;
        this.attendance = attendance;
    }
    public List<AttendanceView> handle(GetChildAttendanceQuery command) {
        authorization.requirePermission("children.read");
        ChildrenRules.page(command.limit(), command.offset());
        return attendance.childAttendance(command.childId(), command.limit(), command.offset())
            .stream()
            .map(AttendanceView::from)
            .toList();
    }
}
