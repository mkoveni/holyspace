package org.onechurch.people.domain;

import java.time.Instant;
import java.util.Objects;
import java.util.UUID;

public record PersonRelationship(UUID id, UUID sourceId, UUID targetId, RelationshipType type, Instant validFrom,
    Instant validTo, long version) {

    public PersonRelationship {
        Objects.requireNonNull(id);
        Objects.requireNonNull(sourceId);
        Objects.requireNonNull(targetId);
        Objects.requireNonNull(type);
        Objects.requireNonNull(validFrom);
        if (sourceId.equals(targetId)) {
            throw PeopleException.invalid("A person cannot be related to themselves.");
        }
        if (type.symmetric() && sourceId.toString().compareTo(targetId.toString()) > 0) {
            UUID original = sourceId;
            sourceId = targetId;
            targetId = original;
        }
        if (validTo != null && !validTo.isAfter(validFrom)) {
            throw PeopleException.invalid("Relationship end must follow its start.");
        }
    }

    public boolean currentAt(Instant at) {
        return !at.isBefore(validFrom) && (validTo == null || at.isBefore(validTo));
    }

    public PersonRelationship end(long expected, Instant now) {
        PeopleRules.version(version, expected);
        if (!currentAt(now) || !now.isAfter(validFrom)) {
            throw PeopleException.conflict("This relationship cannot be ended at this time.");
        }
        return new PersonRelationship(id, sourceId, targetId, type, validFrom, now, version + 1);
    }
}
