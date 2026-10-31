package org.onechurch.people.application.query.searchpeople;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.PersonSummary;
import org.onechurch.people.application.contract.PersonReader;
import org.onechurch.people.domain.PeopleException;
import org.onechurch.people.domain.PeopleRules;

import java.time.Clock;
import java.time.LocalDate;
import java.time.ZoneId;
import java.util.List;

public final class SearchPeopleHandler {

    private final PermissionChecker authorization;
    private final PersonReader persons;
    private final Clock clock;
    private final ZoneId zone;

    public SearchPeopleHandler(PermissionChecker authorization, PersonReader persons, Clock clock, ZoneId zone) {
        this.authorization = authorization;
        this.persons = persons;
        this.clock = clock;
        this.zone = zone;
    }

    public List<PersonSummary> handle(SearchPeopleQuery command) {
        authorization.requirePermission("people.read");
        if (command.limit() < 1 || command.limit() > 100 || command.offset() < 0 || command.offset() > 1000000) {
            throw PeopleException.invalid("Invalid paging parameters.");
        }
        String search = PeopleRules.optionalText(command.search(), "Search", 200);
        LocalDate asOf = command.asOf() == null ? LocalDate.now(clock.withZone(zone)) : command.asOf();
        return persons
            .search(search, command.includeArchived(), command.membershipType(), command.ageCategory(), asOf,
                command.limit(), command.offset())
            .stream()
            .map(person -> PersonSummary.of(person, asOf))
            .toList();
    }
}
