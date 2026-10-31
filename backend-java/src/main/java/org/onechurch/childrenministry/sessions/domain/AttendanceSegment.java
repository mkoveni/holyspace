package org.onechurch.childrenministry.sessions.domain;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;

import java.time.Instant;
import java.util.UUID;
public record AttendanceSegment(UUID id, UUID attendanceId, UUID sessionId, UUID enrolmentId, UUID assignmentId,
    UUID consentId, Instant arrivedAt, Instant leftAt) {
    public AttendanceSegment {
        arrivedAt = ChildrenRules.instant(arrivedAt);
        leftAt = ChildrenRules.instant(leftAt);
    }

}
