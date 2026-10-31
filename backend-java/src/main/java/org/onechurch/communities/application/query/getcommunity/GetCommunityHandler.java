package org.onechurch.communities.application.query.getcommunity;
import org.onechurch.communities.application.CommunityAccess;
import org.onechurch.communities.application.CommunityViews;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

public final class GetCommunityHandler {
    private final CommunityViews service;
    private final CommunityAccess access;
    private final PermissionChecker auth;
    public GetCommunityHandler(CommunityViews service, CommunityAccess access, PermissionChecker auth) {
        this.service = service;
        this.access = access;
        this.auth = auth;
    }
    public CommunityViews.Details handle(GetCommunityQuery q) {
        access.directoryScope();
        return service.detail(q.communityId());
    }
}
