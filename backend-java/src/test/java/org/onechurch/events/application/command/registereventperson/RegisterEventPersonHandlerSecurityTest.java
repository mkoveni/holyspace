package org.onechurch.events.application.command.registereventperson;
import org.onechurch.shared.infrastructure.EventHandlerSecuritySupport;

import java.util.UUID;
class RegisterEventPersonHandlerSecurityTest extends EventHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return RegisterEventPersonHandler.class;
    }
    protected Object input(UUID actor) {
        return new RegisterEventPersonCommand(actor, null, null, null, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
