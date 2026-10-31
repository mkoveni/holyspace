package org.onechurch.communities.application.command.completecommunityfollowup;
import org.onechurch.communities.application.CommunityAccess;
import org.onechurch.communities.application.GroupMeetings;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

public final class CompleteCommunityFollowUpHandler {
    private final GroupMeetings service;
    private final CommunityAccess access;
    private final PermissionChecker auth;
    public CompleteCommunityFollowUpHandler(GroupMeetings service, CommunityAccess access, PermissionChecker auth) {
        this.service = service;
        this.access = access;
        this.auth = auth;
    }
    public void handle(CompleteCommunityFollowUpCommand c) {
        access.actor(c.actorId());
        service.complete(c.actorId(), c.followUpId(), c.expectedVersion(), c.reason());
    }
}
