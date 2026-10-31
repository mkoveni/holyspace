package org.onechurch.payments.application.command.closewebhookoperation;
import org.onechurch.shared.infrastructure.PaymentHandlerSecuritySupport;

import java.util.UUID;
class CloseWebhookOperationHandlerSecurityTest extends PaymentHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return CloseWebhookOperationHandler.class;
    }
    protected Object input(UUID actor) {
        return new CloseWebhookOperationCommand(actor, null, 0, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
