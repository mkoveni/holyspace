package org.onechurch.giving.application.command.createreconciliation;
import org.onechurch.shared.infrastructure.GivingHandlerSecuritySupport;

import java.util.UUID;
class CreateReconciliationHandlerSecurityTest extends GivingHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return CreateReconciliationHandler.class;
    }
    protected Object input(UUID actor) {
        return new CreateReconciliationCommand(actor, null, null, 0, null, null, null, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
