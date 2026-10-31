package org.onechurch.events.application.command.correcteventattendance;
import org.onechurch.events.application.EventAttendanceOperations;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class CorrectEventAttendanceHandler {
    private final PermissionChecker auth;
    private final EventAttendanceOperations attendance;
    public CorrectEventAttendanceHandler(PermissionChecker auth, EventAttendanceOperations attendance) {
        this.auth = auth;
        this.attendance = attendance;
    }
    public void handle(CorrectEventAttendanceCommand c) {
        auth.requirePermission(c.actorId(), "events.attendance.manage");
        attendance.correct(c.actorId(), c.registrationId(), c.expectedVersion(), c.status(), c.reason());
    }
}
