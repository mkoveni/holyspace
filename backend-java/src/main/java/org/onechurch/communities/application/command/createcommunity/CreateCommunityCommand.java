package org.onechurch.communities.application.command.createcommunity;
import java.util.UUID;
public record CreateCommunityCommand(UUID actorId, UUID requestId, String kind, String name, String description,
    UUID parentId, String campus) {}
