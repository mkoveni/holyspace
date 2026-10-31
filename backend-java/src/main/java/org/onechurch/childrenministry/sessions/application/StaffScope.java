package org.onechurch.childrenministry.sessions.application;

import org.onechurch.childrenministry.sessions.application.contract.SessionReader;
import org.onechurch.childrenministry.sessions.domain.Worker;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.util.UUID;
public final class StaffScope {
    private final PermissionChecker authorization;
    private final SessionReader sessions;
    public StaffScope(PermissionChecker authorization, SessionReader sessions) {
        this.authorization = authorization;
        this.sessions = sessions;
    }
    public void require(UUID actorId, UUID sessionId) {
        if (authorization.hasPermission("children.supervise")) {
            return;
        }
        Worker worker = sessions.workerByAccount(actorId)
            .filter(value -> value.revokedAt() == null)
            .orElseThrow(ChildrenException::forbidden);
        boolean assigned = sessions.staff(sessionId)
            .stream()
            .anyMatch(staff -> staff.workerId().equals(worker.personId()) && staff.present());
        if (!assigned) {
            throw ChildrenException.forbidden();
        }
    }
}
