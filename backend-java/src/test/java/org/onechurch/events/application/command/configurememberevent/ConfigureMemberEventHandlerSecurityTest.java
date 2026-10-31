package org.onechurch.events.application.command.configurememberevent;
import org.onechurch.shared.infrastructure.EventDayHandlerSecuritySupport;

import java.util.UUID;
class ConfigureMemberEventHandlerSecurityTest extends EventDayHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ConfigureMemberEventHandler.class;
    }
    protected Object input(UUID actor) {
        return new ConfigureMemberEventCommand(actor, null, true, 0);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
