package org.onechurch.people.domain;

import java.time.Instant;
import java.util.Objects;
import java.util.UUID;

public record HouseholdMembership(UUID id, UUID householdId, UUID personId, boolean primary, Instant validFrom,
    Instant validTo) {

    public HouseholdMembership {
        Objects.requireNonNull(id);
        Objects.requireNonNull(householdId);
        Objects.requireNonNull(personId);
        Objects.requireNonNull(validFrom);
        if (validTo != null && !validTo.isAfter(validFrom)) {
            throw PeopleException.invalid("Membership end must follow its start.");
        }
    }

    public boolean currentAt(Instant at) {
        return !at.isBefore(validFrom) && (validTo == null || at.isBefore(validTo));
    }

    public HouseholdMembership end(Instant now) {
        if (!currentAt(now) || !now.isAfter(validFrom)) {
            throw PeopleException.conflict("This membership cannot be ended at this time.");
        }
        return new HouseholdMembership(id, householdId, personId, primary, validFrom, now);
    }
}
