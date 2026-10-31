package org.onechurch.events.application.command.correcteventattendance;
import org.onechurch.shared.infrastructure.EventDayHandlerSecuritySupport;

import java.util.UUID;
class CorrectEventAttendanceHandlerSecurityTest extends EventDayHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return CorrectEventAttendanceHandler.class;
    }
    protected Object input(UUID actor) {
        return new CorrectEventAttendanceCommand(actor, null, 0, null, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
