package org.onechurch.communities.application.query.getgrouproster;
import org.onechurch.communities.application.CommunityAccess;
import org.onechurch.communities.application.CommunityViews;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

public final class GetGroupRosterHandler {
    private final CommunityViews service;
    private final CommunityAccess access;
    private final PermissionChecker auth;
    public GetGroupRosterHandler(CommunityViews service, CommunityAccess access, PermissionChecker auth) {
        this.service = service;
        this.access = access;
        this.auth = auth;
    }
    public CommunityViews.Roster handle(GetGroupRosterQuery q) {
        access.directoryScope();
        return service.roster(q.meetingId(), q.limit(), q.offset());
    }
}
