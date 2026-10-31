package org.onechurch.childrenministry.sessions.application.query.listsessions;
import org.onechurch.childrenministry.sessions.application.SessionOperations;
import org.onechurch.childrenministry.sessions.application.SessionView;
import org.onechurch.childrenministry.sessions.application.contract.SessionReader;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.time.Clock;
import java.time.Duration;
import java.time.Instant;
import java.util.List;
public final class ListSessionsHandler {
    private final PermissionChecker authorization;
    private final SessionReader session;
    private final SessionOperations operations;
    private final Clock clock;
    public ListSessionsHandler(PermissionChecker authorization, SessionReader session, SessionOperations operations,
        Clock clock) {
        this.authorization = authorization;
        this.session = session;
        this.operations = operations;
        this.clock = clock;
    }
    public List<SessionView> handle(ListSessionsQuery command) {
        authorization.requirePermission("children.read");
        ChildrenRules.page(command.limit(), command.offset());
        Instant now = clock.instant();
        Instant from = command.from() == null ? now.minusSeconds(86400) : command.from();
        Instant until = command.until() == null ? from.plusSeconds(604800) : command.until();
        ChildrenRules.period(from, until);
        if (Duration.between(from, until).compareTo(Duration.ofDays(31)) > 0) {
            throw ChildrenException.invalid("Search at most 31 days at a time.");
        }
        return session.sessions(command.locationId(), from, until, command.limit(), command.offset())
            .stream()
            .map(value -> operations.view(value, now))
            .toList();
    }
}
