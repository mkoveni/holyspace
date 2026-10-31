package org.onechurch.people.application.command.endhouseholdmembership;

import java.util.UUID;

public record EndHouseholdMembershipCommand(UUID actorId, UUID householdId, long expectedVersion, UUID membershipId) {}
