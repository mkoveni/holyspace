package org.onechurch.communities.application.query.getgrouproster;
import org.onechurch.shared.infrastructure.CommunityHandlerSecuritySupport;

import java.util.UUID;
class GetGroupRosterHandlerSecurityTest extends CommunityHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return GetGroupRosterHandler.class;
    }
    protected Object input(UUID actor) {
        return new GetGroupRosterQuery(null, 0, 0);
    }

}
