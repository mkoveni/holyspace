package org.onechurch.events.application.command.canceleventregistration;
import org.onechurch.shared.infrastructure.EventHandlerSecuritySupport;

import java.util.UUID;
class CancelEventRegistrationHandlerSecurityTest extends EventHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return CancelEventRegistrationHandler.class;
    }
    protected Object input(UUID actor) {
        return new CancelEventRegistrationCommand(actor, null, 0, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
