package org.onechurch.events.application.command.configureeventregistration;
import org.onechurch.shared.infrastructure.EventHandlerSecuritySupport;

import java.util.UUID;
class ConfigureEventRegistrationHandlerSecurityTest extends EventHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ConfigureEventRegistrationHandler.class;
    }
    protected Object input(UUID actor) {
        return new ConfigureEventRegistrationCommand(actor, null, 0, false, null, false, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
