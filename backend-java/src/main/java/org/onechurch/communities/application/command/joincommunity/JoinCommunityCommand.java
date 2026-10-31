package org.onechurch.communities.application.command.joincommunity;
import java.util.UUID;
public record JoinCommunityCommand(UUID actorId, UUID requestId, UUID communityId, UUID personId) {}
