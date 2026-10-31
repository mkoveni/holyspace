package org.onechurch.people.application.query.getpersonhouseholds;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.HouseholdReader;
import org.onechurch.people.application.contract.PersonReader;
import org.onechurch.people.domain.HouseholdMembership;
import org.onechurch.people.domain.PeopleException;

import java.util.List;

public final class GetPersonHouseholdsHandler {

    private final PermissionChecker authorization;
    private final PersonReader persons;
    private final HouseholdReader homes;

    public GetPersonHouseholdsHandler(PermissionChecker authorization, PersonReader persons, HouseholdReader homes) {
        this.authorization = authorization;
        this.persons = persons;
        this.homes = homes;
    }

    public List<HouseholdMembership> handle(GetPersonHouseholdsQuery command) {
        authorization.requirePermission("people.read");
        persons.find(command.personId()).orElseThrow(() -> PeopleException.missing("Person"));
        return homes.memberships(command.personId());
    }
}
