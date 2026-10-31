package org.onechurch.payments.application.command.resumecheckout;
import org.onechurch.shared.infrastructure.PaymentHandlerSecuritySupport;

import java.util.UUID;
class ResumeCheckoutHandlerSecurityTest extends PaymentHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ResumeCheckoutHandler.class;
    }
    protected Object input(UUID actor) {
        return new ResumeCheckoutCommand(actor, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
