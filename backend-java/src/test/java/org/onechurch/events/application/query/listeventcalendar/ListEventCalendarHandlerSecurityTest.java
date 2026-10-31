package org.onechurch.events.application.query.listeventcalendar;
import org.onechurch.shared.infrastructure.EventHandlerSecuritySupport;

import java.util.UUID;
class ListEventCalendarHandlerSecurityTest extends EventHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ListEventCalendarHandler.class;
    }
    protected Object input(UUID actor) {
        return new ListEventCalendarQuery(null, null, null, null, null, 0, 0);
    }
}
