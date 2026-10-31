package org.onechurch.volunteering.domain;

import java.time.Instant;
import java.util.UUID;

public record TeamMembership(UUID id, UUID teamId, UUID volunteerId, Instant validFrom, Instant validUntil,
    Instant revokedAt) {
    public TeamMembership {
        validFrom = ServingRules.instant(validFrom);
        validUntil = ServingRules.instant(validUntil);
        ServingRules.period(validFrom, validUntil);
    }
    public boolean covers(Instant from, Instant until) {
        return revokedAt == null && ServingRules.covers(validFrom, validUntil, from, until);
    }
}
