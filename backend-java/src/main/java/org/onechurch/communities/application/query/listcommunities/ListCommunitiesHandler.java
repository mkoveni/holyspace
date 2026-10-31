package org.onechurch.communities.application.query.listcommunities;
import org.onechurch.communities.application.CommunityAccess;
import org.onechurch.communities.application.CommunityViews;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

public final class ListCommunitiesHandler {
    private final CommunityViews service;
    private final CommunityAccess access;
    private final PermissionChecker auth;
    public ListCommunitiesHandler(CommunityViews service, CommunityAccess access, PermissionChecker auth) {
        this.service = service;
        this.access = access;
        this.auth = auth;
    }
    public CommunityViews.Directory handle(ListCommunitiesQuery q) {
        access.directoryScope();
        return service.directory(q.kind(), q.search(), q.archived(), q.limit(), q.offset());
    }
}
