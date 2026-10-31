package org.onechurch.events.application.command.canceleventoccurrences;
import org.onechurch.shared.infrastructure.EventHandlerSecuritySupport;

import java.util.UUID;
class CancelEventOccurrencesHandlerSecurityTest extends EventHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return CancelEventOccurrencesHandler.class;
    }
    protected Object input(UUID actor) {
        return new CancelEventOccurrencesCommand(actor, null, null, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
