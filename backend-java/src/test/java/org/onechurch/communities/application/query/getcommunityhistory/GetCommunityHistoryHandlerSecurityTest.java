package org.onechurch.communities.application.query.getcommunityhistory;
import org.onechurch.shared.infrastructure.CommunityHandlerSecuritySupport;

import java.util.UUID;
class GetCommunityHistoryHandlerSecurityTest extends CommunityHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return GetCommunityHistoryHandler.class;
    }
    protected Object input(UUID actor) {
        return new GetCommunityHistoryQuery(null, 0, 0);
    }

}
