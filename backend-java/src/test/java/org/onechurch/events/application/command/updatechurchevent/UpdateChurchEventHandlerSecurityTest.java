package org.onechurch.events.application.command.updatechurchevent;
import org.onechurch.shared.infrastructure.EventHandlerSecuritySupport;

import java.util.UUID;
class UpdateChurchEventHandlerSecurityTest extends EventHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return UpdateChurchEventHandler.class;
    }
    protected Object input(UUID actor) {
        return new UpdateChurchEventCommand(actor, null, 0, null, null, null, false, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
