package org.onechurch.communities.application.query.getcommunity;
import org.onechurch.shared.infrastructure.CommunityHandlerSecuritySupport;

import java.util.UUID;
class GetCommunityHandlerSecurityTest extends CommunityHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return GetCommunityHandler.class;
    }
    protected Object input(UUID actor) {
        return new GetCommunityQuery(null);
    }

}
