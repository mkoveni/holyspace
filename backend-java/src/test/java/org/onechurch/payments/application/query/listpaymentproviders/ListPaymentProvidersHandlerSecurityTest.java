package org.onechurch.payments.application.query.listpaymentproviders;
import org.onechurch.shared.infrastructure.PaymentHandlerSecuritySupport;

import java.util.UUID;
class ListPaymentProvidersHandlerSecurityTest extends PaymentHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ListPaymentProvidersHandler.class;
    }
    protected Object input(UUID actor) {
        return new ListPaymentProvidersQuery();
    }
}
