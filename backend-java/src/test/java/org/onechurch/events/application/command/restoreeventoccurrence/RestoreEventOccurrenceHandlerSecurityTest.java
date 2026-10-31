package org.onechurch.events.application.command.restoreeventoccurrence;
import org.onechurch.shared.infrastructure.EventHandlerSecuritySupport;

import java.util.UUID;
class RestoreEventOccurrenceHandlerSecurityTest extends EventHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return RestoreEventOccurrenceHandler.class;
    }
    protected Object input(UUID actor) {
        return new RestoreEventOccurrenceCommand(actor, null, null, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
