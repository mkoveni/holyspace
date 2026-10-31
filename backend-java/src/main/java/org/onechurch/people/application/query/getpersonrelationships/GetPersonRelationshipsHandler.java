package org.onechurch.people.application.query.getpersonrelationships;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.RelationshipView;
import org.onechurch.people.application.contract.PersonReader;
import org.onechurch.people.application.contract.RelationshipReader;
import org.onechurch.people.domain.PeopleException;

import java.util.List;

public final class GetPersonRelationshipsHandler {

    private final PermissionChecker authorization;
    private final PersonReader persons;
    private final RelationshipReader links;

    public GetPersonRelationshipsHandler(PermissionChecker authorization, PersonReader persons,
        RelationshipReader links) {
        this.authorization = authorization;
        this.persons = persons;
        this.links = links;
    }

    public List<RelationshipView> handle(GetPersonRelationshipsQuery command) {
        authorization.requirePermission("people.read");
        persons.find(command.personId()).orElseThrow(() -> PeopleException.missing("Person"));
        return links.forPerson(command.personId())
            .stream()
            .map(link -> RelationshipView.from(command.personId(), link))
            .toList();
    }
}
