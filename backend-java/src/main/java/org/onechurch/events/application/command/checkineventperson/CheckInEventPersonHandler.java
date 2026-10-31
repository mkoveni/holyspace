package org.onechurch.events.application.command.checkineventperson;
import org.onechurch.events.application.EventAttendanceOperations;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.util.UUID;
public final class CheckInEventPersonHandler {
    private final PermissionChecker auth;
    private final EventAttendanceOperations attendance;
    public CheckInEventPersonHandler(PermissionChecker auth, EventAttendanceOperations attendance) {
        this.auth = auth;
        this.attendance = attendance;
    }
    public UUID handle(CheckInEventPersonCommand c) {
        auth.requirePermission(c.actorId(), "events.attendance.manage");
        return attendance.checkIn(c.actorId(), c.requestId(), c.registrationId(), c.expectedVersion());
    }
}
