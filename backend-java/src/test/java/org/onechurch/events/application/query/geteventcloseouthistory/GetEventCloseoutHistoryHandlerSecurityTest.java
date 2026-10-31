package org.onechurch.events.application.query.geteventcloseouthistory;
import org.onechurch.shared.infrastructure.EventDayHandlerSecuritySupport;

import java.util.UUID;
class GetEventCloseoutHistoryHandlerSecurityTest extends EventDayHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return GetEventCloseoutHistoryHandler.class;
    }
    protected Object input(UUID actor) {
        return new GetEventCloseoutHistoryQuery(null, 25, 0);
    }

}
