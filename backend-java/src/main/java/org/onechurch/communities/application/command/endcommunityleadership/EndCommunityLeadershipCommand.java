package org.onechurch.communities.application.command.endcommunityleadership;
import java.util.UUID;
public record EndCommunityLeadershipCommand(UUID actorId, UUID leadershipId, long expectedVersion, String reason) {}
