package org.onechurch.events.application.query.geteventoccurrence;
import org.onechurch.shared.infrastructure.EventHandlerSecuritySupport;

import java.util.UUID;
class GetEventOccurrenceHandlerSecurityTest extends EventHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return GetEventOccurrenceHandler.class;
    }
    protected Object input(UUID actor) {
        return new GetEventOccurrenceQuery(null);
    }
}
