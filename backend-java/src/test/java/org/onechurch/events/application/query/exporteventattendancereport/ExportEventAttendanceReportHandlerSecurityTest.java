package org.onechurch.events.application.query.exporteventattendancereport;
import org.onechurch.shared.infrastructure.EventDayHandlerSecuritySupport;

import java.util.UUID;
class ExportEventAttendanceReportHandlerSecurityTest extends EventDayHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ExportEventAttendanceReportHandler.class;
    }
    protected Object input(UUID actor) {
        return new ExportEventAttendanceReportQuery(null, null, null, null);
    }
}
