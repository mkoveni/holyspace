package org.onechurch.giving.application.command.cancelpledge;
import java.util.UUID;
public record CancelPledgeCommand(UUID actorId, UUID pledgeId, long expectedVersion, String reason) {}
