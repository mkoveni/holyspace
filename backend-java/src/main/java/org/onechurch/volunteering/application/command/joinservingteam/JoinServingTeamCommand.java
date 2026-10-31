package org.onechurch.volunteering.application.command.joinservingteam;

import java.time.Instant;
import java.util.UUID;

public record JoinServingTeamCommand(UUID actorId, UUID teamId, long expectedVersion, UUID volunteerId,
    Instant validFrom, Instant validUntil) {}
