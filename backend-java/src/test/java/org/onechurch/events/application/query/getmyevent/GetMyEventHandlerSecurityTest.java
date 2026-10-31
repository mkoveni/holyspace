package org.onechurch.events.application.query.getmyevent;
import org.onechurch.shared.infrastructure.DonorHandlerSecuritySupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class GetMyEventHandlerSecurityTest extends DonorHandlerSecuritySupport {
    @Autowired GetMyEventHandler handler;
    protected Object invoke(UUID actor) {
        return handler.handle(new GetMyEventQuery(UUID.randomUUID()));
    }
}
