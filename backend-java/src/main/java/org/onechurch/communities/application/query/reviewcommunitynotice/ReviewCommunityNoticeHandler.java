package org.onechurch.communities.application.query.reviewcommunitynotice;
import org.onechurch.communities.application.CommunityAccess;
import org.onechurch.communities.application.CommunityMessaging;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

public final class ReviewCommunityNoticeHandler {
    private final CommunityMessaging service;
    private final CommunityAccess access;
    private final PermissionChecker auth;
    public ReviewCommunityNoticeHandler(CommunityMessaging service, CommunityAccess access, PermissionChecker auth) {
        this.service = service;
        this.access = access;
        this.auth = auth;
    }
    public CommunityMessaging.Review handle(ReviewCommunityNoticeQuery q) {
        auth.requirePermission("ministries.communications.send");
        access.directoryScope();
        return service.review(q.membershipId(), q.contactId(), q.message());
    }
}
