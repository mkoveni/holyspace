package org.onechurch.people.application.command.updatehousehold;

import java.util.UUID;

public record UpdateHouseholdCommand(UUID actorId, UUID householdId, long expectedVersion, String name) {}
