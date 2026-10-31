package org.onechurch.events.application.query.listmyevents;
import org.onechurch.shared.infrastructure.DonorHandlerSecuritySupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class ListMyEventsHandlerSecurityTest extends DonorHandlerSecuritySupport {
    @Autowired ListMyEventsHandler handler;
    protected Object invoke(UUID actor) {
        return handler.handle(new ListMyEventsQuery(false, 25, 0));
    }
}
