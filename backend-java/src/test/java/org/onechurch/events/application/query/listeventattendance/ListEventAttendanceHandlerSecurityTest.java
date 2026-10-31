package org.onechurch.events.application.query.listeventattendance;
import org.onechurch.shared.infrastructure.EventDayHandlerSecuritySupport;

import java.util.UUID;
class ListEventAttendanceHandlerSecurityTest extends EventDayHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ListEventAttendanceHandler.class;
    }
    protected Object input(UUID actor) {
        return new ListEventAttendanceQuery(null, null, null, 0, 0);
    }
}
