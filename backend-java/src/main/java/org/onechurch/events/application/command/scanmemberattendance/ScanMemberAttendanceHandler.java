package org.onechurch.events.application.command.scanmemberattendance;
import org.onechurch.events.application.MemberEventActions;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.util.UUID;
public final class ScanMemberAttendanceHandler {
    private final MemberEventActions actions;
    private final PermissionChecker auth;
    public ScanMemberAttendanceHandler(MemberEventActions actions, PermissionChecker auth) {
        this.actions = actions;
        this.auth = auth;
    }
    public UUID handle(ScanMemberAttendanceCommand c) {
        auth.requirePermission(c.actorId(), "events.attendance.manage");
        return actions.scan(c.actorId(), c.occurrenceId(), c.requestId(), c.credential());
    }
}
