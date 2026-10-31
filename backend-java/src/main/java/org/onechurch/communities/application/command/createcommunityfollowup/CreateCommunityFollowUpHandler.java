package org.onechurch.communities.application.command.createcommunityfollowup;
import org.onechurch.communities.application.CommunityAccess;
import org.onechurch.communities.application.GroupMeetings;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.util.UUID;

public final class CreateCommunityFollowUpHandler {
    private final GroupMeetings service;
    private final CommunityAccess access;
    private final PermissionChecker auth;
    public CreateCommunityFollowUpHandler(GroupMeetings service, CommunityAccess access, PermissionChecker auth) {
        this.service = service;
        this.access = access;
        this.auth = auth;
    }
    public UUID handle(CreateCommunityFollowUpCommand c) {
        access.actor(c.actorId());
        return service.followup(c.actorId(), c.requestId(), c.communityId(), c.personId(), c.note(), c.dueOn());
    }
}
