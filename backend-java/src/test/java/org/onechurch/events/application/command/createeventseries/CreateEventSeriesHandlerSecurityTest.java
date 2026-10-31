package org.onechurch.events.application.command.createeventseries;
import org.onechurch.shared.infrastructure.EventHandlerSecuritySupport;

import java.util.UUID;
class CreateEventSeriesHandlerSecurityTest extends EventHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return CreateEventSeriesHandler.class;
    }
    protected Object input(UUID actor) {
        return new CreateEventSeriesCommand(actor, null, null, 0, null, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
