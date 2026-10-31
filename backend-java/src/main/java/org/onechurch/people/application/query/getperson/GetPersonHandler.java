package org.onechurch.people.application.query.getperson;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.PersonView;
import org.onechurch.people.application.contract.PersonReader;
import org.onechurch.people.domain.PeopleException;
import org.onechurch.people.domain.Person;

import java.time.Clock;
import java.time.LocalDate;
import java.time.ZoneId;

public final class GetPersonHandler {

    private final PermissionChecker authorization;
    private final PersonReader persons;
    private final Clock clock;
    private final ZoneId zone;

    public GetPersonHandler(PermissionChecker authorization, PersonReader persons, Clock clock, ZoneId zone) {
        this.authorization = authorization;
        this.persons = persons;
        this.clock = clock;
        this.zone = zone;
    }

    public PersonView handle(GetPersonQuery command) {
        authorization.requirePermission("people.read");
        Person person = persons.find(command.personId()).orElseThrow(() -> PeopleException.missing("Person"));
        LocalDate asOf = command.asOf() == null ? LocalDate.now(clock.withZone(zone)) : command.asOf();
        return PersonView.of(person, asOf, persons.contacts(person.id()), persons.addresses(person.id()));
    }
}
