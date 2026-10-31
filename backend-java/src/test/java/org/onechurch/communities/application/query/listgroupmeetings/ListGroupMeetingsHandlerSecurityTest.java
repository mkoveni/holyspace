package org.onechurch.communities.application.query.listgroupmeetings;
import org.onechurch.shared.infrastructure.CommunityHandlerSecuritySupport;

import java.util.UUID;
class ListGroupMeetingsHandlerSecurityTest extends CommunityHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ListGroupMeetingsHandler.class;
    }
    protected Object input(UUID actor) {
        return new ListGroupMeetingsQuery(null, 0, 0);
    }

}
