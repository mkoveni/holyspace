package org.onechurch.giving.application.command.reviewreconciliation;
import org.onechurch.shared.infrastructure.GivingHandlerSecuritySupport;

import java.util.UUID;
class ReviewReconciliationHandlerSecurityTest extends GivingHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ReviewReconciliationHandler.class;
    }
    protected Object input(UUID actor) {
        return new ReviewReconciliationCommand(actor, null, null, 0, null, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
