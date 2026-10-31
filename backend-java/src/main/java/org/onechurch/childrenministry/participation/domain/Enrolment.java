package org.onechurch.childrenministry.participation.domain;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;

import java.time.Instant;
import java.time.LocalDate;
import java.util.UUID;
public record Enrolment(UUID id, UUID programmeId, UUID childId, LocalDate validFrom, LocalDate validUntil,
    Instant endedAt, long version, Instant createdAt) {
    public Enrolment {
        endedAt = ChildrenRules.instant(endedAt);
        createdAt = ChildrenRules.instant(createdAt);
        ChildrenRules.period(validFrom, validUntil);
    }

    public boolean covers(LocalDate on) {
        return endedAt == null && !on.isBefore(validFrom) && on.isBefore(validUntil);
    }
    public Enrolment end(long expected, Instant now) {
        ChildrenRules.version(version, expected);
        if (endedAt != null) {
            throw ChildrenException.conflict("This participation already ended.");
        }
        return new Enrolment(id, programmeId, childId, validFrom, validUntil, now, version + 1, createdAt);
    }
}
