package org.onechurch.communities.application.command.endcommunityleadership;
import org.onechurch.communities.application.CommunityAccess;
import org.onechurch.communities.application.CommunityManagement;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

public final class EndCommunityLeadershipHandler {
    private final CommunityManagement service;
    private final CommunityAccess access;
    private final PermissionChecker auth;
    public EndCommunityLeadershipHandler(CommunityManagement service, CommunityAccess access, PermissionChecker auth) {
        this.service = service;
        this.access = access;
        this.auth = auth;
    }
    public void handle(EndCommunityLeadershipCommand c) {
        access.manager(c.actorId());
        service.endLeadership(c.actorId(), c.leadershipId(), c.expectedVersion(), c.reason());
    }
}
