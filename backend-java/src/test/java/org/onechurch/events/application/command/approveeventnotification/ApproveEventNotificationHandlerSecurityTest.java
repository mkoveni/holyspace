package org.onechurch.events.application.command.approveeventnotification;
import org.onechurch.shared.infrastructure.EventDayHandlerSecuritySupport;

import java.util.UUID;
class ApproveEventNotificationHandlerSecurityTest extends EventDayHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ApproveEventNotificationHandler.class;
    }
    protected Object input(UUID actor) {
        return new ApproveEventNotificationCommand(actor, null, null, null, null, null, null, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
