package org.onechurch.communities.application.command.recordgroupattendance;
import org.onechurch.communities.application.CommunityAccess;
import org.onechurch.communities.application.GroupMeetings;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

public final class RecordGroupAttendanceHandler {
    private final GroupMeetings service;
    private final CommunityAccess access;
    private final PermissionChecker auth;
    public RecordGroupAttendanceHandler(GroupMeetings service, CommunityAccess access, PermissionChecker auth) {
        this.service = service;
        this.access = access;
        this.auth = auth;
    }
    public void handle(RecordGroupAttendanceCommand c) {
        access.actor(c.actorId());
        service.attendance(c.actorId(), c.rosterId(), c.expectedVersion(), c.status(), c.reason());
    }
}
