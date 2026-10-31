package org.onechurch.payments.application.command.reconcilepayment;
import org.onechurch.shared.infrastructure.PaymentHandlerSecuritySupport;

import java.util.UUID;
class ReconcilePaymentHandlerSecurityTest extends PaymentHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ReconcilePaymentHandler.class;
    }
    protected Object input(UUID actor) {
        return new ReconcilePaymentCommand(actor, null, null, null, 0, null, 0, 0, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
