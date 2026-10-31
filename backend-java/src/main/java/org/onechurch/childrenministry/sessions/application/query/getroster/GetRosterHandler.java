package org.onechurch.childrenministry.sessions.application.query.getroster;

import org.onechurch.childrenministry.sessions.application.AttendanceView;
import org.onechurch.childrenministry.sessions.application.StaffScope;
import org.onechurch.childrenministry.sessions.application.contract.AttendanceReader;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.util.List;
public final class GetRosterHandler {
    private final PermissionChecker authorization;
    private final StaffScope scope;
    private final AttendanceReader attendance;
    public GetRosterHandler(PermissionChecker authorization, StaffScope scope, AttendanceReader attendance) {
        this.authorization = authorization;
        this.scope = scope;
        this.attendance = attendance;
    }
    public List<AttendanceView> handle(GetRosterQuery command) {
        authorization.requirePermission("children.station");
        authorization.requirePermission(command.actorId(), "children.station");
        scope.require(command.actorId(), command.sessionId());
        ChildrenRules.page(command.limit(), command.offset());
        return attendance.roster(command.sessionId(), command.openOnly(), command.limit(), command.offset())
            .stream()
            .map(AttendanceView::from)
            .toList();
    }
}
