package org.onechurch.volunteering.domain;

import java.time.Instant;
import java.util.UUID;

public record AvailabilityException(UUID id, UUID volunteerId, long revision, Instant startsAt, Instant endsAt,
    boolean available) {
    public AvailabilityException {
        startsAt = ServingRules.instant(startsAt);
        endsAt = ServingRules.instant(endsAt);
        ServingRules.period(startsAt, endsAt);
    }
}
