package org.onechurch.communities.application.command.addgroupguest;
import org.onechurch.communities.application.CommunityAccess;
import org.onechurch.communities.application.GroupMeetings;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

public final class AddGroupGuestHandler {
    private final GroupMeetings service;
    private final CommunityAccess access;
    private final PermissionChecker auth;
    public AddGroupGuestHandler(GroupMeetings service, CommunityAccess access, PermissionChecker auth) {
        this.service = service;
        this.access = access;
        this.auth = auth;
    }
    public void handle(AddGroupGuestCommand c) {
        access.actor(c.actorId());
        service.guest(c.actorId(), c.meetingId(), c.expectedVersion(), c.personId(), c.reason());
    }
}
