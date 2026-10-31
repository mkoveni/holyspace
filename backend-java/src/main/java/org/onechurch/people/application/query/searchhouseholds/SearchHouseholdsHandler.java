package org.onechurch.people.application.query.searchhouseholds;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.HouseholdReader;
import org.onechurch.people.domain.Household;
import org.onechurch.people.domain.PeopleException;
import org.onechurch.people.domain.PeopleRules;

import java.util.List;

public final class SearchHouseholdsHandler {

    private final PermissionChecker authorization;
    private final HouseholdReader homes;

    public SearchHouseholdsHandler(PermissionChecker authorization, HouseholdReader homes) {
        this.authorization = authorization;
        this.homes = homes;
    }

    public List<Household> handle(SearchHouseholdsQuery command) {
        authorization.requirePermission("people.read");
        if (command.limit() < 1 || command.limit() > 100 || command.offset() < 0 || command.offset() > 1000000) {
            throw PeopleException.invalid("Invalid paging parameters.");
        }
        return homes.search(PeopleRules.optionalText(command.search(), "Search", 200), command.limit(),
            command.offset());
    }
}
