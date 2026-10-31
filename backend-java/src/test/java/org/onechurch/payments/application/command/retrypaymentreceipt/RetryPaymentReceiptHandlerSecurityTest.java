package org.onechurch.payments.application.command.retrypaymentreceipt;
import org.onechurch.shared.infrastructure.PaymentHandlerSecuritySupport;

import java.util.UUID;
class RetryPaymentReceiptHandlerSecurityTest extends PaymentHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return RetryPaymentReceiptHandler.class;
    }
    protected Object input(UUID actor) {
        return new RetryPaymentReceiptCommand(actor, null, null, 0, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
