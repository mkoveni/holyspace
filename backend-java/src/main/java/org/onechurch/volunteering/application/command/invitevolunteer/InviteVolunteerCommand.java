package org.onechurch.volunteering.application.command.invitevolunteer;

import java.time.Instant;
import java.util.UUID;

public record InviteVolunteerCommand(UUID actorId, UUID assignmentId, long expectedVersion, Instant expiresAt) {}
