package org.onechurch.communities.application.query.listcommunityleadership;
import org.onechurch.shared.infrastructure.CommunityHandlerSecuritySupport;

import java.util.UUID;
class ListCommunityLeadershipHandlerSecurityTest extends CommunityHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ListCommunityLeadershipHandler.class;
    }
    protected Object input(UUID actor) {
        return new ListCommunityLeadershipQuery(null, 0, 0);
    }

}
