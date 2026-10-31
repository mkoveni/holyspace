package org.onechurch.payments.application.command.removeyocowebhook;
import org.onechurch.shared.infrastructure.PaymentHandlerSecuritySupport;

import java.util.UUID;
class RemoveYocoWebhookHandlerSecurityTest extends PaymentHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return RemoveYocoWebhookHandler.class;
    }
    protected Object input(UUID actor) {
        return new RemoveYocoWebhookCommand(actor, null, null, 0, null, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
