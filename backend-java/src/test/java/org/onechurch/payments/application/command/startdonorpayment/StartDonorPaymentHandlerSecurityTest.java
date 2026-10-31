package org.onechurch.payments.application.command.startdonorpayment;
import org.onechurch.shared.infrastructure.DonorHandlerSecuritySupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class StartDonorPaymentHandlerSecurityTest extends DonorHandlerSecuritySupport {
    @Autowired StartDonorPaymentHandler handler;
    protected Object invoke(UUID actor) {
        return handler
            .handle(new StartDonorPaymentCommand(actor, UUID.randomUUID(), UUID.randomUUID(), 10000, "payfast", false));
    }
}
