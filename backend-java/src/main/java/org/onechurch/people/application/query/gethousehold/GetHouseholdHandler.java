package org.onechurch.people.application.query.gethousehold;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.HouseholdView;
import org.onechurch.people.application.contract.HouseholdReader;
import org.onechurch.people.domain.Household;
import org.onechurch.people.domain.PeopleException;

public final class GetHouseholdHandler {

    private final PermissionChecker authorization;
    private final HouseholdReader homes;

    public GetHouseholdHandler(PermissionChecker authorization, HouseholdReader homes) {
        this.authorization = authorization;
        this.homes = homes;
    }

    public HouseholdView handle(GetHouseholdQuery command) {
        authorization.requirePermission("people.read");
        Household household = homes.find(command.householdId()).orElseThrow(() -> PeopleException.missing("Household"));
        return new HouseholdView(household, homes.members(household.id()));
    }
}
