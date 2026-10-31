package org.onechurch.events.application.query.geteventattendancereport;
import org.onechurch.shared.infrastructure.EventDayHandlerSecuritySupport;

import java.util.UUID;
class GetEventAttendanceReportHandlerSecurityTest extends EventDayHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return GetEventAttendanceReportHandler.class;
    }
    protected Object input(UUID actor) {
        return new GetEventAttendanceReportQuery(null, null, null, null, 0, 0);
    }
}
