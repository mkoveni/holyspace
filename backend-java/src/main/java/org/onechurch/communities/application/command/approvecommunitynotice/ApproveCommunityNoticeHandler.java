package org.onechurch.communities.application.command.approvecommunitynotice;
import org.onechurch.communities.application.CommunityAccess;
import org.onechurch.communities.application.CommunityMessaging;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.util.UUID;

public final class ApproveCommunityNoticeHandler {
    private final CommunityMessaging service;
    private final CommunityAccess access;
    private final PermissionChecker auth;
    public ApproveCommunityNoticeHandler(CommunityMessaging service, CommunityAccess access, PermissionChecker auth) {
        this.service = service;
        this.access = access;
        this.auth = auth;
    }
    public UUID handle(ApproveCommunityNoticeCommand c) {
        auth.requirePermission(c.actorId(), "ministries.communications.send");
        access.actor(c.actorId());
        return service.approve(c.actorId(), c.requestId(), c.membershipId(), c.contactId(), c.message(), c.reviewHash(),
            c.evidence());
    }
}
