package org.onechurch.childrenministry.sessions.domain;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;

import java.time.Instant;
import java.util.UUID;
public record AttendanceEvent(UUID id, UUID attendanceId, UUID actorId, String type, String reason,
    Instant occurredAt) {
    public AttendanceEvent {
        occurredAt = ChildrenRules.instant(occurredAt);
    }

}
