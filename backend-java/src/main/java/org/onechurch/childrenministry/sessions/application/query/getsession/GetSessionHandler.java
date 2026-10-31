package org.onechurch.childrenministry.sessions.application.query.getsession;

import org.onechurch.childrenministry.sessions.application.SessionOperations;
import org.onechurch.childrenministry.sessions.application.SessionView;
import org.onechurch.childrenministry.sessions.application.contract.SessionReader;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.time.Clock;
public final class GetSessionHandler {
    private final PermissionChecker authorization;
    private final SessionReader session;
    private final SessionOperations operations;
    private final Clock clock;
    public GetSessionHandler(PermissionChecker authorization, SessionReader session, SessionOperations operations,
        Clock clock) {
        this.authorization = authorization;
        this.session = session;
        this.operations = operations;
        this.clock = clock;
    }
    public SessionView handle(GetSessionQuery command) {
        authorization.requirePermission("children.read");
        return operations.view(session.ministrySession(command.sessionId())
            .orElseThrow(() -> ChildrenException.missing("MinistrySession")), clock.instant());
    }
}
