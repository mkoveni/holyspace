package org.onechurch.volunteering.application.command.respondvolunteerinvitation;

import java.util.UUID;

public record RespondVolunteerInvitationCommand(UUID actorId, UUID invitationId, long expectedVersion,
    String decision) {}
