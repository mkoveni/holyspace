package org.onechurch.communities.application.command.createcommunityfollowup;
import java.time.LocalDate;
import java.util.UUID;
public record CreateCommunityFollowUpCommand(UUID actorId, UUID requestId, UUID communityId, UUID personId, String note,
    LocalDate dueOn) {}
