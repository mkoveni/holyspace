package org.onechurch.communities.application.query.reviewcommunitynotice;
import org.onechurch.shared.infrastructure.CommunityHandlerSecuritySupport;

import java.util.UUID;
class ReviewCommunityNoticeHandlerSecurityTest extends CommunityHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ReviewCommunityNoticeHandler.class;
    }
    protected Object input(UUID actor) {
        return new ReviewCommunityNoticeQuery(null, null, null);
    }

}
