package org.onechurch.events.application.command.registereventwalkin;
import org.onechurch.events.application.EventAttendanceOperations;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class RegisterEventWalkInHandler {
    private final PermissionChecker auth;
    private final EventAttendanceOperations attendance;
    public RegisterEventWalkInHandler(PermissionChecker auth, EventAttendanceOperations attendance) {
        this.auth = auth;
        this.attendance = attendance;
    }
    public EventAttendanceOperations.WalkIn handle(RegisterEventWalkInCommand c) {
        auth.requirePermission(c.actorId(), "events.attendance.manage");
        return attendance.walkIn(c.actorId(), c.requestId(), c.occurrenceId(), c.personId(), c.expectedVersion());
    }
}
