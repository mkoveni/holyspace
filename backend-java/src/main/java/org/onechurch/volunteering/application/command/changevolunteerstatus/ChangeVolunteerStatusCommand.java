package org.onechurch.volunteering.application.command.changevolunteerstatus;

import java.util.UUID;

public record ChangeVolunteerStatusCommand(UUID actorId, UUID id, long expectedVersion, String status) {}
