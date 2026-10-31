package org.onechurch.events.application.command.updateeventvenue;
import org.onechurch.shared.infrastructure.EventHandlerSecuritySupport;

import java.util.UUID;
class UpdateEventVenueHandlerSecurityTest extends EventHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return UpdateEventVenueHandler.class;
    }
    protected Object input(UUID actor) {
        return new UpdateEventVenueCommand(actor, null, 0, null, null, false);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
