package org.onechurch.communities.application.command.finishgroupmeeting;
import org.onechurch.communities.application.CommunityAccess;
import org.onechurch.communities.application.GroupMeetings;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

public final class FinishGroupMeetingHandler {
    private final GroupMeetings service;
    private final CommunityAccess access;
    private final PermissionChecker auth;
    public FinishGroupMeetingHandler(GroupMeetings service, CommunityAccess access, PermissionChecker auth) {
        this.service = service;
        this.access = access;
        this.auth = auth;
    }
    public void handle(FinishGroupMeetingCommand c) {
        access.actor(c.actorId());
        service.finish(c.actorId(), c.meetingId(), c.expectedVersion(), c.status(), c.reason());
    }
}
