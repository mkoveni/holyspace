package org.onechurch.communities.application.query.getcommunityhistory;
import org.onechurch.communities.application.CommunityAccess;
import org.onechurch.communities.application.CommunityViews;
import org.onechurch.communities.application.contract.CommunityReader;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.util.List;

public final class GetCommunityHistoryHandler {
    private final CommunityViews service;
    private final CommunityAccess access;
    private final PermissionChecker auth;
    public GetCommunityHistoryHandler(CommunityViews service, CommunityAccess access, PermissionChecker auth) {
        this.service = service;
        this.access = access;
        this.auth = auth;
    }
    public List<CommunityReader.History> handle(GetCommunityHistoryQuery q) {
        access.directoryScope();
        return service.history(q.communityId(), q.limit(), q.offset());
    }
}
