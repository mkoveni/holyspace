package org.onechurch.communities.application.query.listcommunityfollowups;
import org.onechurch.shared.infrastructure.CommunityHandlerSecuritySupport;

import java.util.UUID;
class ListCommunityFollowUpsHandlerSecurityTest extends CommunityHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ListCommunityFollowUpsHandler.class;
    }
    protected Object input(UUID actor) {
        return new ListCommunityFollowUpsQuery(null, false, 0, 0);
    }

}
