package org.onechurch.communities.application.command.updatecommunity;
import java.util.UUID;
public record UpdateCommunityCommand(UUID actorId, UUID communityId, long expectedVersion, String name,
    String description, UUID parentId, String campus, boolean active, String reason) {}
