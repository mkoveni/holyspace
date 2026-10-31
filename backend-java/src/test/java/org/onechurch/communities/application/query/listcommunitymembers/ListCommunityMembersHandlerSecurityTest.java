package org.onechurch.communities.application.query.listcommunitymembers;
import org.onechurch.shared.infrastructure.CommunityHandlerSecuritySupport;

import java.util.UUID;
class ListCommunityMembersHandlerSecurityTest extends CommunityHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ListCommunityMembersHandler.class;
    }
    protected Object input(UUID actor) {
        return new ListCommunityMembersQuery(null, false, 0, 0);
    }

}
