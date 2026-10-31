package org.onechurch.events.application.command.closeeventoccurrence;
import org.onechurch.shared.infrastructure.EventDayHandlerSecuritySupport;

import java.util.UUID;
class CloseEventOccurrenceHandlerSecurityTest extends EventDayHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return CloseEventOccurrenceHandler.class;
    }
    protected Object input(UUID actor) {
        return new CloseEventOccurrenceCommand(actor, null, 0, null, null, null, false, false);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
