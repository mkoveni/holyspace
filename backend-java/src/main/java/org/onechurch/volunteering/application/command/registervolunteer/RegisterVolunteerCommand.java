package org.onechurch.volunteering.application.command.registervolunteer;

import java.util.UUID;

public record RegisterVolunteerCommand(UUID actorId, UUID personId, UUID accountId) {}
