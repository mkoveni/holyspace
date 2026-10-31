package org.onechurch.events.application.query.getchurchevent;
import org.onechurch.shared.infrastructure.EventHandlerSecuritySupport;

import java.util.UUID;
class GetChurchEventHandlerSecurityTest extends EventHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return GetChurchEventHandler.class;
    }
    protected Object input(UUID actor) {
        return new GetChurchEventQuery(null, 0, 0);
    }
}
