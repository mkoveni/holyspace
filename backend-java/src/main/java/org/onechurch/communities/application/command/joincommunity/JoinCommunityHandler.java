package org.onechurch.communities.application.command.joincommunity;
import org.onechurch.communities.application.CommunityAccess;
import org.onechurch.communities.application.CommunityManagement;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.util.UUID;

public final class JoinCommunityHandler {
    private final CommunityManagement service;
    private final CommunityAccess access;
    private final PermissionChecker auth;
    public JoinCommunityHandler(CommunityManagement service, CommunityAccess access, PermissionChecker auth) {
        this.service = service;
        this.access = access;
        this.auth = auth;
    }
    public UUID handle(JoinCommunityCommand c) {
        access.manager(c.actorId());
        return service.join(c.actorId(), c.requestId(), c.communityId(), c.personId());
    }
}
