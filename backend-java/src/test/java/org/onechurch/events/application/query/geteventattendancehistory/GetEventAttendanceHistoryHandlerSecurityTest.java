package org.onechurch.events.application.query.geteventattendancehistory;
import org.onechurch.shared.infrastructure.EventDayHandlerSecuritySupport;

import java.util.UUID;
class GetEventAttendanceHistoryHandlerSecurityTest extends EventDayHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return GetEventAttendanceHistoryHandler.class;
    }
    protected Object input(UUID actor) {
        return new GetEventAttendanceHistoryQuery(null, 0, 0);
    }
}
