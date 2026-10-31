package org.onechurch.volunteering.domain;

import java.time.Instant;
import java.util.UUID;

public record Credential(UUID id, UUID volunteerId, UUID typeId, Instant validFrom, Instant validUntil, String evidence,
    UUID verifiedBy, Instant verifiedAt, Instant revokedAt) {
    public Credential {
        validFrom = ServingRules.instant(validFrom);
        validUntil = ServingRules.instant(validUntil);
        ServingRules.period(validFrom, validUntil);
        evidence = ServingRules.text(evidence, 500);
    }
    public boolean covers(Instant from, Instant until) {
        return revokedAt == null && ServingRules.covers(validFrom, validUntil, from, until);
    }
    @Override
    public String toString() {
        return "Credential[id=" + id + "]";
    }
}
