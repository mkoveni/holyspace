package org.onechurch.childrenministry.sessions.application.query.getsessioncloseout;
import org.onechurch.childrenministry.incidents.application.contract.IncidentReader;
import org.onechurch.childrenministry.sessions.application.contract.SessionCloseoutReader;
import org.onechurch.childrenministry.sessions.application.contract.SessionReader;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.time.Clock;
import java.time.ZoneId;
public final class GetSessionCloseoutHandler {
    private final PermissionChecker authorization;
    private final SessionReader sessions;
    private final SessionCloseoutReader reader;
    private final Clock clock;
    private final IncidentReader incidents;
    private final ZoneId zone;
    public GetSessionCloseoutHandler(PermissionChecker authorization, SessionReader sessions,
        SessionCloseoutReader reader, Clock clock, IncidentReader incidents, ZoneId zone) {
        this.authorization = authorization;
        this.sessions = sessions;
        this.reader = reader;
        this.clock = clock;
        this.incidents = incidents;
        this.zone = zone;
    }
    public SessionCloseout handle(GetSessionCloseoutQuery query) {
        authorization.requirePermission(query.actorId(), "children.manage");
        sessions.ministrySession(query.sessionId()).orElseThrow(() -> ChildrenException.missing("Session"));
        SessionCloseout summary = reader.summarize(query.sessionId(), clock.instant());
        return authorization.hasPermission("children.incidents.manage")
            ? summary.withIncidents(incidents.counts(query.sessionId(), clock.instant().atZone(zone).toLocalDate()))
            : summary;
    }
}
