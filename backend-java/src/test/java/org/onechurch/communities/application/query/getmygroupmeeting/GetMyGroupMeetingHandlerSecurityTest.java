package org.onechurch.communities.application.query.getmygroupmeeting;
import org.onechurch.shared.infrastructure.DonorHandlerSecuritySupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class GetMyGroupMeetingHandlerSecurityTest extends DonorHandlerSecuritySupport {
    @Autowired GetMyGroupMeetingHandler handler;
    protected Object invoke(UUID actor) {
        return handler.handle(new GetMyGroupMeetingQuery(UUID.randomUUID()));
    }
}
