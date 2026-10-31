package org.onechurch.giving.application.command.createfund;
import org.onechurch.shared.infrastructure.GivingHandlerSecuritySupport;

import java.util.UUID;
class CreateFundHandlerSecurityTest extends GivingHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return CreateFundHandler.class;
    }
    protected Object input(UUID actor) {
        return new CreateFundCommand(actor, null, null, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
