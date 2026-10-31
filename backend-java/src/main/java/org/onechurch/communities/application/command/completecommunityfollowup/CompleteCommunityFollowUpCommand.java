package org.onechurch.communities.application.command.completecommunityfollowup;
import java.util.UUID;
public record CompleteCommunityFollowUpCommand(UUID actorId, UUID followUpId, long expectedVersion, String reason) {}
