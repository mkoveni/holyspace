package org.onechurch.volunteering.application.command.assignvolunteer;

import java.util.UUID;

public record AssignVolunteerCommand(UUID actorId, UUID requestId, UUID shiftId, long expectedVersion, UUID volunteerId,
    String status) {}
