package org.onechurch.payments.application.query.getpayment;
import org.onechurch.shared.infrastructure.PaymentHandlerSecuritySupport;

import java.util.UUID;
class GetPaymentHandlerSecurityTest extends PaymentHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return GetPaymentHandler.class;
    }
    protected Object input(UUID actor) {
        return new GetPaymentQuery(null);
    }
}
