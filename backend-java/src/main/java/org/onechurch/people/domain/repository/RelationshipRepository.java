package org.onechurch.people.domain.repository;

import org.onechurch.people.domain.PersonRelationship;

import java.time.Instant;
import java.util.List;
import java.util.Optional;
import java.util.UUID;

public interface RelationshipRepository {
    void lockGraph();
    Optional<PersonRelationship> find(UUID id);
    List<PersonRelationship> currentParentage(Instant at);
    void insert(PersonRelationship relationship);
    void update(PersonRelationship relationship);
}
