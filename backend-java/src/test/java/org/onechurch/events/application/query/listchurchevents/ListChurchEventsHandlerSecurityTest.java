package org.onechurch.events.application.query.listchurchevents;
import org.onechurch.shared.infrastructure.EventHandlerSecuritySupport;

import java.util.UUID;
class ListChurchEventsHandlerSecurityTest extends EventHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ListChurchEventsHandler.class;
    }
    protected Object input(UUID actor) {
        return new ListChurchEventsQuery(null, false, 0, 0);
    }
}
