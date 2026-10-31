package org.onechurch.people.application;

import org.onechurch.people.domain.PersonRelationship;

import java.time.Instant;
import java.util.UUID;

public record RelationshipView(UUID id, UUID relatedPersonId, String type, Instant validFrom, Instant validTo,
    long version) {

    public static RelationshipView from(UUID perspective, PersonRelationship relationship) {
        boolean outgoing = perspective.equals(relationship.sourceId());
        return new RelationshipView(relationship.id(), outgoing ? relationship.targetId() : relationship.sourceId(),
            outgoing ? relationship.type().name() : relationship.type().inverse(), relationship.validFrom(),
            relationship.validTo(), relationship.version());
    }
}
