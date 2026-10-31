package org.onechurch.childrenministry.sessions.application.query.liststationsessions;
import org.onechurch.childrenministry.sessions.application.SessionOperations;
import org.onechurch.childrenministry.sessions.application.SessionView;
import org.onechurch.childrenministry.sessions.application.contract.SessionReader;
import org.onechurch.childrenministry.sessions.domain.MinistrySession;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.time.Clock;
import java.time.Duration;
import java.time.Instant;
import java.util.List;
public final class ListStationSessionsHandler {
    private final PermissionChecker authorization;
    private final SessionReader sessions;
    private final SessionOperations operations;
    private final Clock clock;
    public ListStationSessionsHandler(PermissionChecker authorization, SessionReader sessions,
        SessionOperations operations, Clock clock) {
        this.authorization = authorization;
        this.sessions = sessions;
        this.operations = operations;
        this.clock = clock;
    }
    public List<SessionView> handle(ListStationSessionsQuery query) {
        authorization.requirePermission(query.actorId(), "children.station");
        ChildrenRules.page(query.limit(), query.offset());
        Instant now = clock.instant();
        Instant from = query.from() == null ? now.minusSeconds(86400) : query.from();
        Instant until = query.until() == null ? from.plusSeconds(604800) : query.until();
        ChildrenRules.period(from, until);
        if (Duration.between(from, until).compareTo(Duration.ofDays(31)) > 0) {
            throw ChildrenException.invalid("Search at most 31 days at a time.");
        }
        List<MinistrySession> result = authorization.hasPermission("children.supervise")
            ? sessions.sessions(null, from, until, query.limit(), query.offset())
            : sessions.stationSessions(query.actorId(), from, until, query.limit(), query.offset());
        return result.stream().map(session -> operations.view(session, now)).toList();
    }
}
