package org.onechurch.communities.application.query.listcommunityfollowups;
import org.onechurch.communities.application.CommunityAccess;
import org.onechurch.communities.application.CommunityViews;
import org.onechurch.communities.application.contract.CommunityReader;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.util.List;

public final class ListCommunityFollowUpsHandler {
    private final CommunityViews service;
    private final CommunityAccess access;
    private final PermissionChecker auth;
    public ListCommunityFollowUpsHandler(CommunityViews service, CommunityAccess access, PermissionChecker auth) {
        this.service = service;
        this.access = access;
        this.auth = auth;
    }
    public List<CommunityReader.FollowUp> handle(ListCommunityFollowUpsQuery q) {
        access.directoryScope();
        return service.followups(q.communityId(), q.completed(), q.limit(), q.offset());
    }
}
