package org.onechurch.communities.application.query.previewgroupschedule;
import org.onechurch.communities.application.CommunityAccess;
import org.onechurch.communities.application.GroupMeetings;
import org.onechurch.events.application.contract.CommunityCalendar;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

public final class PreviewGroupScheduleHandler {
    private final GroupMeetings service;
    private final CommunityAccess access;
    private final PermissionChecker auth;
    public PreviewGroupScheduleHandler(GroupMeetings service, CommunityAccess access, PermissionChecker auth) {
        this.service = service;
        this.access = access;
        this.auth = auth;
    }
    public CommunityCalendar.Preview handle(PreviewGroupScheduleQuery q) {
        access.directoryScope();
        return service.preview(q.communityId(), q.schedule());
    }
}
