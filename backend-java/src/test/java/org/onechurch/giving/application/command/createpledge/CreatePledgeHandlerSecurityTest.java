package org.onechurch.giving.application.command.createpledge;
import org.onechurch.shared.infrastructure.GivingHandlerSecuritySupport;

import java.util.UUID;
class CreatePledgeHandlerSecurityTest extends GivingHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return CreatePledgeHandler.class;
    }
    protected Object input(UUID actor) {
        return new CreatePledgeCommand(actor, null, null, null, 0, null, null, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
