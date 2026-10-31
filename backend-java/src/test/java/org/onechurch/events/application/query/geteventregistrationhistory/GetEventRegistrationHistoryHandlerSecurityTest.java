package org.onechurch.events.application.query.geteventregistrationhistory;
import org.onechurch.shared.infrastructure.EventHandlerSecuritySupport;

import java.util.UUID;
class GetEventRegistrationHistoryHandlerSecurityTest extends EventHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return GetEventRegistrationHistoryHandler.class;
    }
    protected Object input(UUID actor) {
        return new GetEventRegistrationHistoryQuery(null, 0, 0);
    }
}
