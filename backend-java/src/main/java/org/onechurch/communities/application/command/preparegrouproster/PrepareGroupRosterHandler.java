package org.onechurch.communities.application.command.preparegrouproster;
import org.onechurch.communities.application.CommunityAccess;
import org.onechurch.communities.application.GroupMeetings;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

public final class PrepareGroupRosterHandler {
    private final GroupMeetings service;
    private final CommunityAccess access;
    private final PermissionChecker auth;
    public PrepareGroupRosterHandler(GroupMeetings service, CommunityAccess access, PermissionChecker auth) {
        this.service = service;
        this.access = access;
        this.auth = auth;
    }
    public void handle(PrepareGroupRosterCommand c) {
        access.actor(c.actorId());
        service.prepare(c.actorId(), c.meetingId(), c.expectedVersion());
    }
}
