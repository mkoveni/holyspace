package org.onechurch.communities.application.query.listmygroupmeetings;
import org.onechurch.shared.infrastructure.DonorHandlerSecuritySupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class ListMyGroupMeetingsHandlerSecurityTest extends DonorHandlerSecuritySupport {
    @Autowired ListMyGroupMeetingsHandler handler;
    protected Object invoke(UUID actor) {
        return handler.handle(new ListMyGroupMeetingsQuery(null, 25, 0));
    }
}
