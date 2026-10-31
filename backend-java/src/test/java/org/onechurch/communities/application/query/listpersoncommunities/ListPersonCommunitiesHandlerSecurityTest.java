package org.onechurch.communities.application.query.listpersoncommunities;
import org.onechurch.shared.infrastructure.CommunityHandlerSecuritySupport;

import java.util.UUID;
class ListPersonCommunitiesHandlerSecurityTest extends CommunityHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ListPersonCommunitiesHandler.class;
    }
    protected Object input(UUID actor) {
        return new ListPersonCommunitiesQuery(null, 25, 0);
    }
}
