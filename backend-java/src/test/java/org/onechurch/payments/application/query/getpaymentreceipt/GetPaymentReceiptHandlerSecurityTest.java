package org.onechurch.payments.application.query.getpaymentreceipt;
import org.onechurch.shared.infrastructure.PaymentHandlerSecuritySupport;

import java.util.UUID;
class GetPaymentReceiptHandlerSecurityTest extends PaymentHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return GetPaymentReceiptHandler.class;
    }
    protected Object input(UUID actor) {
        return new GetPaymentReceiptQuery(null, null, null);
    }
}
