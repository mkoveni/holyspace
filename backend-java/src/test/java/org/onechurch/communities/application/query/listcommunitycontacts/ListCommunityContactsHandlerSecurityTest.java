package org.onechurch.communities.application.query.listcommunitycontacts;
import org.onechurch.shared.infrastructure.CommunityHandlerSecuritySupport;

import java.util.UUID;
class ListCommunityContactsHandlerSecurityTest extends CommunityHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ListCommunityContactsHandler.class;
    }
    protected Object input(UUID actor) {
        return new ListCommunityContactsQuery(null);
    }

}
