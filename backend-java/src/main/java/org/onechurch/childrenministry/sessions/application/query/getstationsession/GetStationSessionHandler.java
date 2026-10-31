package org.onechurch.childrenministry.sessions.application.query.getstationsession;
import org.onechurch.childrenministry.sessions.application.SessionOperations;
import org.onechurch.childrenministry.sessions.application.SessionView;
import org.onechurch.childrenministry.sessions.application.StaffScope;
import org.onechurch.childrenministry.sessions.application.contract.SessionReader;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.time.Clock;
public final class GetStationSessionHandler {
    private final PermissionChecker authorization;
    private final SessionReader sessions;
    private final SessionOperations operations;
    private final StaffScope scope;
    private final Clock clock;
    public GetStationSessionHandler(PermissionChecker authorization, SessionReader sessions,
        SessionOperations operations, StaffScope scope, Clock clock) {
        this.authorization = authorization;
        this.sessions = sessions;
        this.operations = operations;
        this.scope = scope;
        this.clock = clock;
    }
    public SessionView handle(GetStationSessionQuery query) {
        authorization.requirePermission(query.actorId(), "children.station");
        scope.require(query.actorId(), query.sessionId());
        return operations.view(
            sessions.ministrySession(query.sessionId()).orElseThrow(() -> ChildrenException.missing("Session")),
            clock.instant());
    }
}
