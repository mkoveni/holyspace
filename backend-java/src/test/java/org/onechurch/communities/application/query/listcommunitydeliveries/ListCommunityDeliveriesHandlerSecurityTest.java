package org.onechurch.communities.application.query.listcommunitydeliveries;
import org.onechurch.shared.infrastructure.CommunityHandlerSecuritySupport;

import java.util.UUID;
class ListCommunityDeliveriesHandlerSecurityTest extends CommunityHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ListCommunityDeliveriesHandler.class;
    }
    protected Object input(UUID actor) {
        return new ListCommunityDeliveriesQuery(null, 25, 0);
    }
}
