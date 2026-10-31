package org.onechurch.childrenministry.sessions.application.query.listsessions;

import org.onechurch.childrenministry.shared.domain.ChildrenRules;

import java.time.Instant;
import java.util.UUID;
public record ListSessionsQuery(UUID locationId, Instant from, Instant until, int limit, int offset) {
    public ListSessionsQuery {
        from = ChildrenRules.instant(from);
        until = ChildrenRules.instant(until);
    }
}
