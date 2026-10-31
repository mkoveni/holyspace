package org.onechurch.communities.application.command.appointcommunityleader;
import org.onechurch.communities.application.CommunityAccess;
import org.onechurch.communities.application.CommunityManagement;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.util.UUID;

public final class AppointCommunityLeaderHandler {
    private final CommunityManagement service;
    private final CommunityAccess access;
    private final PermissionChecker auth;
    public AppointCommunityLeaderHandler(CommunityManagement service, CommunityAccess access, PermissionChecker auth) {
        this.service = service;
        this.access = access;
        this.auth = auth;
    }
    public UUID handle(AppointCommunityLeaderCommand c) {
        access.manager(c.actorId());
        return service.appoint(c.actorId(), c.requestId(), c.membershipId(), c.expectedVersion(), c.role(),
            c.accountId(), c.reason());
    }
}
