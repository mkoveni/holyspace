package org.onechurch.communities.application.query.listgroupmeetings;
import org.onechurch.communities.application.CommunityAccess;
import org.onechurch.communities.application.CommunityViews;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

public final class ListGroupMeetingsHandler {
    private final CommunityViews service;
    private final CommunityAccess access;
    private final PermissionChecker auth;
    public ListGroupMeetingsHandler(CommunityViews service, CommunityAccess access, PermissionChecker auth) {
        this.service = service;
        this.access = access;
        this.auth = auth;
    }
    public CommunityViews.Meetings handle(ListGroupMeetingsQuery q) {
        access.directoryScope();
        return service.meetings(q.communityId(), q.limit(), q.offset());
    }
}
