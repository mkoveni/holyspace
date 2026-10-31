package org.onechurch.payments.application.query.getpaymentsettings;
import org.onechurch.shared.infrastructure.PaymentHandlerSecuritySupport;

import java.util.UUID;
class GetPaymentSettingsHandlerSecurityTest extends PaymentHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return GetPaymentSettingsHandler.class;
    }
    protected Object input(UUID actor) {
        return new GetPaymentSettingsQuery();
    }
}
