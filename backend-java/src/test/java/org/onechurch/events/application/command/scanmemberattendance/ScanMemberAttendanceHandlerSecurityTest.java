package org.onechurch.events.application.command.scanmemberattendance;
import org.onechurch.shared.infrastructure.EventDayHandlerSecuritySupport;

import java.util.UUID;
class ScanMemberAttendanceHandlerSecurityTest extends EventDayHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ScanMemberAttendanceHandler.class;
    }
    protected Object input(UUID actor) {
        return new ScanMemberAttendanceCommand(actor, null, null, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
