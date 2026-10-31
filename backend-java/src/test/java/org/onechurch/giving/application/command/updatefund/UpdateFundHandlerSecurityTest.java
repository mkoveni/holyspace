package org.onechurch.giving.application.command.updatefund;
import org.onechurch.shared.infrastructure.GivingHandlerSecuritySupport;

import java.util.UUID;
class UpdateFundHandlerSecurityTest extends GivingHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return UpdateFundHandler.class;
    }
    protected Object input(UUID actor) {
        return new UpdateFundCommand(actor, null, 0, null, null, true, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
