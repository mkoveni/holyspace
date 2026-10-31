package org.onechurch.giving.application.query.listdonoraccess;
import org.onechurch.shared.infrastructure.PaymentHandlerSecuritySupport;

import java.util.UUID;
class ListDonorAccessHandlerSecurityTest extends PaymentHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ListDonorAccessHandler.class;
    }
    protected Object input(UUID actor) {
        return new ListDonorAccessQuery(25, 0);
    }
}
