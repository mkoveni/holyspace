package org.onechurch.communities.application.command.approvecommunitynotice;
import java.util.UUID;
public record ApproveCommunityNoticeCommand(UUID actorId, UUID requestId, UUID membershipId, UUID contactId,
    String message, String reviewHash, String evidence) {}
