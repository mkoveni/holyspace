package org.onechurch.volunteering.application.command.approveservingrole;

import java.time.Instant;
import java.util.UUID;

public record ApproveServingRoleCommand(UUID actorId, UUID teamId, long expectedVersion, UUID volunteerId, UUID roleId,
    Instant validFrom, Instant validUntil) {}
