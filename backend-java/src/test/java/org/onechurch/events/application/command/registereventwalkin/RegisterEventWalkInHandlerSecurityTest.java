package org.onechurch.events.application.command.registereventwalkin;
import org.onechurch.shared.infrastructure.EventDayHandlerSecuritySupport;

import java.util.UUID;
class RegisterEventWalkInHandlerSecurityTest extends EventDayHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return RegisterEventWalkInHandler.class;
    }
    protected Object input(UUID actor) {
        return new RegisterEventWalkInCommand(actor, null, null, null, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
