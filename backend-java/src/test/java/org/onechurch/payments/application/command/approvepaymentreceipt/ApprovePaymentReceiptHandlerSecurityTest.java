package org.onechurch.payments.application.command.approvepaymentreceipt;
import org.onechurch.shared.infrastructure.PaymentHandlerSecuritySupport;

import java.util.UUID;
class ApprovePaymentReceiptHandlerSecurityTest extends PaymentHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ApprovePaymentReceiptHandler.class;
    }
    protected Object input(UUID actor) {
        return new ApprovePaymentReceiptCommand(actor, null, null, null, null, null, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
