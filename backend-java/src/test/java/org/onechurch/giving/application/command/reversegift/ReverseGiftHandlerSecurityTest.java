package org.onechurch.giving.application.command.reversegift;
import org.onechurch.shared.infrastructure.GivingHandlerSecuritySupport;

import java.util.UUID;
class ReverseGiftHandlerSecurityTest extends GivingHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ReverseGiftHandler.class;
    }
    protected Object input(UUID actor) {
        return new ReverseGiftCommand(actor, null, null, null, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
