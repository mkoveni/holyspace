package org.onechurch.communities.application.command.recordgroupattendance;
import org.onechurch.shared.infrastructure.CommunityHandlerSecuritySupport;

import java.util.UUID;
class RecordGroupAttendanceHandlerSecurityTest extends CommunityHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return RecordGroupAttendanceHandler.class;
    }
    protected Object input(UUID actor) {
        return new RecordGroupAttendanceCommand(actor, null, 0, null, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
