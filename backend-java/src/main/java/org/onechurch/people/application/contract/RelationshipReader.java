package org.onechurch.people.application.contract;

import org.onechurch.people.domain.PersonRelationship;

import java.util.List;
import java.util.UUID;

public interface RelationshipReader {
    List<PersonRelationship> forPerson(UUID id);
}
