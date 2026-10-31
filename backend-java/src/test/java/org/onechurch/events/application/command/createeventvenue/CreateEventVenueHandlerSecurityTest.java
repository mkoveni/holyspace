package org.onechurch.events.application.command.createeventvenue;
import org.onechurch.shared.infrastructure.EventHandlerSecuritySupport;

import java.util.UUID;
class CreateEventVenueHandlerSecurityTest extends EventHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return CreateEventVenueHandler.class;
    }
    protected Object input(UUID actor) {
        return new CreateEventVenueCommand(actor, null, null, false);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
