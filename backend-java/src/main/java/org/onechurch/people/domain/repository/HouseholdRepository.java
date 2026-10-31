package org.onechurch.people.domain.repository;

import org.onechurch.people.domain.Household;
import org.onechurch.people.domain.HouseholdMembership;

import java.util.Optional;
import java.util.UUID;

public interface HouseholdRepository {
    Optional<Household> findForUpdate(UUID id);
    Optional<HouseholdMembership> member(UUID householdId, UUID membershipId);
    void insert(Household household);
    void update(Household household);
    void add(HouseholdMembership membership);
    void end(HouseholdMembership membership);
}
