package org.onechurch.childrenministry.participation.domain;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;

import java.time.Instant;
import java.time.LocalDate;
import java.util.UUID;
public record ClassAssignment(UUID id, UUID programmeId, UUID classId, UUID childId, UUID enrolmentId,
    LocalDate validFrom, LocalDate validUntil, Instant endedAt, long version) {
    public ClassAssignment {
        endedAt = ChildrenRules.instant(endedAt);
        ChildrenRules.period(validFrom, validUntil);
    }

    public boolean covers(LocalDate on) {
        return endedAt == null && !on.isBefore(validFrom) && on.isBefore(validUntil);
    }
    public ClassAssignment end(long expected, Instant now) {
        ChildrenRules.version(version, expected);
        if (endedAt != null) {
            throw ChildrenException.conflict("This participation already ended.");
        }
        return new ClassAssignment(id, programmeId, classId, childId, enrolmentId, validFrom, validUntil, now,
            version + 1);
    }
}
