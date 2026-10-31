package org.onechurch.childrenministry.sessions.domain;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;

import java.time.Instant;
import java.util.UUID;
public record SessionSeries(UUID id, String name, UUID sourceSessionId, UUID classId, UUID roomId, int seatLimit,
    String timeZone, WeeklyRecurrence recurrence, Instant createdAt, UUID createdBy, UUID requestId,
    String requestFingerprint, boolean stopped, long version) {
    public SessionSeries {
        name = ChildrenRules.text(name, 120);
        createdAt = ChildrenRules.instant(createdAt);
    }
    public SessionSeries stop(long expectedVersion) {
        ChildrenRules.version(version, expectedVersion);
        if (stopped) {
            throw ChildrenException.conflict("Remaining planned dates have already been cancelled.");
        }
        return new SessionSeries(id, name, sourceSessionId, classId, roomId, seatLimit, timeZone, recurrence, createdAt,
            createdBy, requestId, requestFingerprint, true, version + 1);
    }
}
