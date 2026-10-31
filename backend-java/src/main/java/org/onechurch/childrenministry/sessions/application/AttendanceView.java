package org.onechurch.childrenministry.sessions.application;

import org.onechurch.childrenministry.sessions.domain.Attendance;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;

import java.time.Instant;
import java.util.UUID;
public record AttendanceView(UUID id, UUID childId, UUID sessionId, Instant arrivedAt, Instant checkedOutAt,
    UUID collectorId, UUID authorizationId, boolean identityVerified, boolean exceptionUsed, int labelVersion,
    Instant labelExpiresAt, boolean labelIssued, long version) {
    public AttendanceView {
        arrivedAt = ChildrenRules.instant(arrivedAt);
        checkedOutAt = ChildrenRules.instant(checkedOutAt);
        labelExpiresAt = ChildrenRules.instant(labelExpiresAt);
    }
    public static AttendanceView from(Attendance value) {
        return new AttendanceView(value.id(), value.childId(), value.sessionId(), value.arrivedAt(),
            value.checkedOutAt(), value.collectorId(), value.authorizationId(), value.identityVerified(),
            value.exceptionReason() != null && !value.exceptionReason().isBlank(), value.labelVersion(),
            value.labelExpiresAt(), value.labelHash() != null, value.version());
    }
}
