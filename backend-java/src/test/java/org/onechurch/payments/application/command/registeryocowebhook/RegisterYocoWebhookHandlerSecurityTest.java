package org.onechurch.payments.application.command.registeryocowebhook;
import org.onechurch.shared.infrastructure.PaymentHandlerSecuritySupport;

import java.util.UUID;
class RegisterYocoWebhookHandlerSecurityTest extends PaymentHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return RegisterYocoWebhookHandler.class;
    }
    protected Object input(UUID actor) {
        return new RegisterYocoWebhookCommand(actor, null, null, 0, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
