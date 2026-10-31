package org.onechurch.communities.application.command.leavecommunity;
import java.util.UUID;
public record LeaveCommunityCommand(UUID actorId, UUID membershipId, long expectedVersion, String reason) {}
