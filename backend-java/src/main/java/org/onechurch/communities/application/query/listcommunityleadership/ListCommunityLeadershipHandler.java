package org.onechurch.communities.application.query.listcommunityleadership;
import org.onechurch.communities.application.CommunityAccess;
import org.onechurch.communities.application.CommunityViews;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.util.List;

public final class ListCommunityLeadershipHandler {
    private final CommunityViews service;
    private final CommunityAccess access;
    private final PermissionChecker auth;
    public ListCommunityLeadershipHandler(CommunityViews service, CommunityAccess access, PermissionChecker auth) {
        this.service = service;
        this.access = access;
        this.auth = auth;
    }
    public List<CommunityViews.Appointment> handle(ListCommunityLeadershipQuery q) {
        access.directoryScope();
        return service.leadership(q.communityId(), q.limit(), q.offset());
    }
}
