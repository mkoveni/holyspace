package org.onechurch.communities.application.command.updatecommunity;
import org.onechurch.communities.application.CommunityAccess;
import org.onechurch.communities.application.CommunityManagement;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

public final class UpdateCommunityHandler {
    private final CommunityManagement service;
    private final CommunityAccess access;
    private final PermissionChecker auth;
    public UpdateCommunityHandler(CommunityManagement service, CommunityAccess access, PermissionChecker auth) {
        this.service = service;
        this.access = access;
        this.auth = auth;
    }
    public void handle(UpdateCommunityCommand c) {
        access.manager(c.actorId());
        service.update(c.actorId(), c.communityId(), c.expectedVersion(), c.name(), c.description(), c.parentId(),
            c.campus(), c.active(), c.reason());
    }
}
