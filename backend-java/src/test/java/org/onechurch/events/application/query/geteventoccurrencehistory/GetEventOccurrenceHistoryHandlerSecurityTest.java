package org.onechurch.events.application.query.geteventoccurrencehistory;
import org.onechurch.shared.infrastructure.EventHandlerSecuritySupport;

import java.util.UUID;
class GetEventOccurrenceHistoryHandlerSecurityTest extends EventHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return GetEventOccurrenceHistoryHandler.class;
    }
    protected Object input(UUID actor) {
        return new GetEventOccurrenceHistoryQuery(null, 0, 0);
    }
}
