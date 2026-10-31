package org.onechurch.people.application;

import org.onechurch.people.domain.Household;
import org.onechurch.people.domain.HouseholdMembership;

import java.util.List;

public record HouseholdView(Household household, List<HouseholdMembership> memberships) {

    public HouseholdView {
        memberships = List.copyOf(memberships);
    }
}
