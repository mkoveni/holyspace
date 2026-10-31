package org.onechurch.payments.application.query.listpayments;
import org.onechurch.shared.infrastructure.PaymentHandlerSecuritySupport;

import java.util.UUID;
class ListPaymentsHandlerSecurityTest extends PaymentHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ListPaymentsHandler.class;
    }
    protected Object input(UUID actor) {
        return new ListPaymentsQuery(25, 0, false);
    }
}
