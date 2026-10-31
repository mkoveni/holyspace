package org.onechurch.events.application.command.rescheduleeventoccurrences;
import org.onechurch.shared.infrastructure.EventHandlerSecuritySupport;

import java.util.UUID;
class RescheduleEventOccurrencesHandlerSecurityTest extends EventHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return RescheduleEventOccurrencesHandler.class;
    }
    protected Object input(UUID actor) {
        return new RescheduleEventOccurrencesCommand(actor, null, null, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
