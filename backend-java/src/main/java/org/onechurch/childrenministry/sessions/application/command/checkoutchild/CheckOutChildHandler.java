package org.onechurch.childrenministry.sessions.application.command.checkoutchild;

import org.onechurch.childrenministry.sessions.application.AttendanceView;
import org.onechurch.childrenministry.sessions.application.Handover;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

public final class CheckOutChildHandler {
    private final PermissionChecker authorization;
    private final Handover handover;
    public CheckOutChildHandler(PermissionChecker authorization, Handover handover) {
        this.authorization = authorization;
        this.handover = handover;
    }
    public AttendanceView handle(CheckOutChildCommand command) {
        authorization.requirePermission(command.actorId(), "children.station");
        return handover.collect(command.actorId(), command.attendanceId(), command.expectedVersion(),
            command.requestId(), command.collectorId(), command.identityVerified(), command.pickupCode(), null);
    }
}
