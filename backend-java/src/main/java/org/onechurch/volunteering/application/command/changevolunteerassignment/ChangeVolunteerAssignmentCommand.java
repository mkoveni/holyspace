package org.onechurch.volunteering.application.command.changevolunteerassignment;

import java.util.UUID;

public record ChangeVolunteerAssignmentCommand(UUID actorId, UUID id, long expectedVersion, String status,
    String reason) {}
