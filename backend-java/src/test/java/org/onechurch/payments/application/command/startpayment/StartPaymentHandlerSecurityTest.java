package org.onechurch.payments.application.command.startpayment;
import org.onechurch.shared.infrastructure.PaymentHandlerSecuritySupport;

import java.util.UUID;
class StartPaymentHandlerSecurityTest extends PaymentHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return StartPaymentHandler.class;
    }
    protected Object input(UUID actor) {
        return new StartPaymentCommand(actor, null, null, null, 0, null, false);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
