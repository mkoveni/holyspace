package org.onechurch.childrenministry.sessions.application.command.supervisorcheckout;

import org.onechurch.childrenministry.sessions.application.AttendanceView;
import org.onechurch.childrenministry.sessions.application.Handover;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

public final class SupervisorCheckoutHandler {
    private final PermissionChecker authorization;
    private final Handover handover;
    public SupervisorCheckoutHandler(PermissionChecker authorization, Handover handover) {
        this.authorization = authorization;
        this.handover = handover;
    }
    public AttendanceView handle(SupervisorCheckoutCommand command) {
        authorization.requirePermission(command.actorId(), "children.supervise");
        return handover.collect(command.actorId(), command.attendanceId(), command.expectedVersion(),
            command.requestId(), command.collectorId(), command.identityVerified(), null,
            ChildrenRules.text(command.reason(), 500));
    }
}
