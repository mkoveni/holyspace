package org.onechurch.childrenministry.sessions.application.query.getworker;

import org.onechurch.childrenministry.sessions.application.contract.SessionReader;
import org.onechurch.childrenministry.sessions.domain.Worker;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

public final class GetWorkerHandler {
    private final PermissionChecker authorization;
    private final SessionReader session;
    public GetWorkerHandler(PermissionChecker authorization, SessionReader session) {
        this.authorization = authorization;
        this.session = session;
    }
    public Worker handle(GetWorkerQuery command) {
        authorization.requirePermission("children.safeguarding");
        return session.worker(command.personId()).orElseThrow(() -> ChildrenException.missing("Worker"));
    }
}
