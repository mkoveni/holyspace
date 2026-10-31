package org.onechurch.volunteering.domain;

import java.time.Instant;
import java.util.Set;
import java.util.UUID;

public record Assignment(UUID id, UUID requestId, String requestHash, UUID shiftId, UUID volunteerId, String status,
    long version, UUID createdBy, Instant createdAt) {
    public Assignment {
        ServingRules.id(id);
        ServingRules.id(requestId);
        ServingRules.id(shiftId);
        ServingRules.id(volunteerId);
        ServingRules.id(createdBy);
        createdAt = ServingRules.instant(createdAt);
        if (!Set.of("PROPOSED", "CONFIRMED", "CANCELLED").contains(status)) {
            throw VolunteerException.invalid("Choose a proposed or confirmed assignment.");
        }
    }
    public Assignment change(String next) {
        if (status.equals("CANCELLED")) {
            throw VolunteerException.conflict("A cancelled assignment cannot reopen.");
        }
        return new Assignment(id, requestId, requestHash, shiftId, volunteerId, next, version + 1, createdBy,
            createdAt);
    }
}
