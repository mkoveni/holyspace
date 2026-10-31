package org.onechurch.communities.application.query.listcommunitymembers;
import org.onechurch.communities.application.CommunityAccess;
import org.onechurch.communities.application.CommunityViews;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

public final class ListCommunityMembersHandler {
    private final CommunityViews service;
    private final CommunityAccess access;
    private final PermissionChecker auth;
    public ListCommunityMembersHandler(CommunityViews service, CommunityAccess access, PermissionChecker auth) {
        this.service = service;
        this.access = access;
        this.auth = auth;
    }
    public CommunityViews.Members handle(ListCommunityMembersQuery q) {
        access.directoryScope();
        return service.members(q.communityId(), q.ended(), q.limit(), q.offset());
    }
}
