package org.onechurch.people.application.command.sethouseholdcontact;

import java.util.UUID;

public record SetHouseholdContactCommand(UUID actorId, UUID householdId, long expectedVersion, UUID membershipId) {}
