package org.onechurch.communities.application.command.leavecommunity;
import org.onechurch.communities.application.CommunityAccess;
import org.onechurch.communities.application.CommunityManagement;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

public final class LeaveCommunityHandler {
    private final CommunityManagement service;
    private final CommunityAccess access;
    private final PermissionChecker auth;
    public LeaveCommunityHandler(CommunityManagement service, CommunityAccess access, PermissionChecker auth) {
        this.service = service;
        this.access = access;
        this.auth = auth;
    }
    public void handle(LeaveCommunityCommand c) {
        access.manager(c.actorId());
        service.leave(c.actorId(), c.membershipId(), c.expectedVersion(), c.reason());
    }
}
