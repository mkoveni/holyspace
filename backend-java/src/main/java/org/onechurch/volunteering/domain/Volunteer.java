package org.onechurch.volunteering.domain;

import java.time.Instant;
import java.util.Set;
import java.util.UUID;

public record Volunteer(UUID id, UUID personId, UUID accountId, String status, long availabilityRevision, long version,
    Instant createdAt, Instant updatedAt) {
    public Volunteer {
        ServingRules.id(id);
        ServingRules.id(personId);
        if (status == null || !Set.of("ACTIVE", "PAUSED", "ARCHIVED").contains(status)) {
            throw VolunteerException.invalid("Choose active, paused or archived.");
        }
    }
    public Volunteer change(String status, Instant at) {
        return new Volunteer(id, personId, accountId, status, availabilityRevision, version + 1, createdAt, at);
    }
    public Volunteer availability(Instant at) {
        return new Volunteer(id, personId, accountId, status, availabilityRevision + 1, version + 1, createdAt, at);
    }
    public Volunteer touch(Instant at) {
        return new Volunteer(id, personId, accountId, status, availabilityRevision, version + 1, createdAt, at);
    }
}
