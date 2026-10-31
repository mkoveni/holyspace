package org.onechurch.people.application.command.createhousehold;

import java.util.UUID;

public record CreateHouseholdCommand(UUID actorId, String name) {}
