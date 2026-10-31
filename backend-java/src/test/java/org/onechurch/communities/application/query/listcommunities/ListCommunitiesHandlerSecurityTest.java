package org.onechurch.communities.application.query.listcommunities;
import org.onechurch.shared.infrastructure.CommunityHandlerSecuritySupport;

import java.util.UUID;
class ListCommunitiesHandlerSecurityTest extends CommunityHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ListCommunitiesHandler.class;
    }
    protected Object input(UUID actor) {
        return new ListCommunitiesQuery(null, null, false, 0, 0);
    }

}
