package org.onechurch.communities.application.command.schedulegroupmeetings;
import org.onechurch.communities.application.CommunityAccess;
import org.onechurch.communities.application.GroupMeetings;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.util.UUID;

public final class ScheduleGroupMeetingsHandler {
    private final GroupMeetings service;
    private final CommunityAccess access;
    private final PermissionChecker auth;
    public ScheduleGroupMeetingsHandler(GroupMeetings service, CommunityAccess access, PermissionChecker auth) {
        this.service = service;
        this.access = access;
        this.auth = auth;
    }
    public UUID handle(ScheduleGroupMeetingsCommand c) {
        access.manager(c.actorId());
        return service.schedule(c.actorId(), c.requestId(), c.communityId(), c.expectedVersion(), c.schedule(),
            c.reviewHash());
    }
}
