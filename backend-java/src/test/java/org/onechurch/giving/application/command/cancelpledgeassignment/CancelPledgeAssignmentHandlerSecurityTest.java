package org.onechurch.giving.application.command.cancelpledgeassignment;
import org.onechurch.shared.infrastructure.GivingHandlerSecuritySupport;

import java.util.UUID;
class CancelPledgeAssignmentHandlerSecurityTest extends GivingHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return CancelPledgeAssignmentHandler.class;
    }
    protected Object input(UUID actor) {
        return new CancelPledgeAssignmentCommand(actor, null, 0, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
