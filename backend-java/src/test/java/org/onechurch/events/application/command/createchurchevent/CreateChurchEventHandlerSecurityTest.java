package org.onechurch.events.application.command.createchurchevent;
import org.onechurch.shared.infrastructure.EventHandlerSecuritySupport;

import java.util.UUID;
class CreateChurchEventHandlerSecurityTest extends EventHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return CreateChurchEventHandler.class;
    }
    protected Object input(UUID actor) {
        return new CreateChurchEventCommand(actor, null, null, null, null, null, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
