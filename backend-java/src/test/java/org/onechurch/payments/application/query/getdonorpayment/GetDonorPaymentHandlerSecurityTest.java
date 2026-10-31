package org.onechurch.payments.application.query.getdonorpayment;
import org.onechurch.shared.infrastructure.DonorHandlerSecuritySupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class GetDonorPaymentHandlerSecurityTest extends DonorHandlerSecuritySupport {
    @Autowired GetDonorPaymentHandler handler;
    protected Object invoke(UUID actor) {
        return handler.handle(new GetDonorPaymentQuery(UUID.randomUUID()));
    }
}
