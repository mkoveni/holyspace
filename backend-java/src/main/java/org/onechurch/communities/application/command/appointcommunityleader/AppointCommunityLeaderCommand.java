package org.onechurch.communities.application.command.appointcommunityleader;
import java.util.UUID;
public record AppointCommunityLeaderCommand(UUID actorId, UUID requestId, UUID membershipId, long expectedVersion,
    String role, UUID accountId, String reason) {}
