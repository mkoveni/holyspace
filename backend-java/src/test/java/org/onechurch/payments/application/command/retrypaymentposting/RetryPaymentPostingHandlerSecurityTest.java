package org.onechurch.payments.application.command.retrypaymentposting;
import org.onechurch.shared.infrastructure.PaymentHandlerSecuritySupport;

import java.util.UUID;
class RetryPaymentPostingHandlerSecurityTest extends PaymentHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return RetryPaymentPostingHandler.class;
    }
    protected Object input(UUID actor) {
        return new RetryPaymentPostingCommand(actor, null, 0);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
