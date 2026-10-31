package org.onechurch.events.application.command.checkineventperson;
import org.onechurch.shared.infrastructure.EventDayHandlerSecuritySupport;

import java.util.UUID;
class CheckInEventPersonHandlerSecurityTest extends EventDayHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return CheckInEventPersonHandler.class;
    }
    protected Object input(UUID actor) {
        return new CheckInEventPersonCommand(actor, null, null, 0);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
