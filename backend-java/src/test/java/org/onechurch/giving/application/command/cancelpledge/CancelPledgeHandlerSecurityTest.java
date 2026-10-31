package org.onechurch.giving.application.command.cancelpledge;
import org.onechurch.shared.infrastructure.GivingHandlerSecuritySupport;

import java.util.UUID;
class CancelPledgeHandlerSecurityTest extends GivingHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return CancelPledgeHandler.class;
    }
    protected Object input(UUID actor) {
        return new CancelPledgeCommand(actor, null, 0, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
