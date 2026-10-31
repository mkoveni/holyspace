package org.onechurch.people.application.contract;

import org.onechurch.people.domain.Household;
import org.onechurch.people.domain.HouseholdMembership;

import java.util.List;
import java.util.Optional;
import java.util.UUID;

public interface HouseholdReader {
    Optional<Household> find(UUID id);
    List<Household> search(String search, int limit, int offset);
    List<HouseholdMembership> members(UUID id);
    List<HouseholdMembership> memberships(UUID personId);
}
