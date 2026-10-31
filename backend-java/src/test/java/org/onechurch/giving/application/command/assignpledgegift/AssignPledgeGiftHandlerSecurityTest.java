package org.onechurch.giving.application.command.assignpledgegift;
import org.onechurch.shared.infrastructure.GivingHandlerSecuritySupport;

import java.util.UUID;
class AssignPledgeGiftHandlerSecurityTest extends GivingHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return AssignPledgeGiftHandler.class;
    }
    protected Object input(UUID actor) {
        return new AssignPledgeGiftCommand(actor, null, null, null, 0, 0);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
