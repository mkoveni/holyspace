package org.onechurch.events.application.command.reopeneventcloseout;
import org.onechurch.shared.infrastructure.EventDayHandlerSecuritySupport;

import java.util.UUID;
class ReopenEventCloseoutHandlerSecurityTest extends EventDayHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ReopenEventCloseoutHandler.class;
    }
    protected Object input(UUID actor) {
        return new ReopenEventCloseoutCommand(actor, null, 0, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
