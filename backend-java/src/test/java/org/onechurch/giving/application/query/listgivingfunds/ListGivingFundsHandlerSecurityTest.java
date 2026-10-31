package org.onechurch.giving.application.query.listgivingfunds;
import org.onechurch.shared.infrastructure.GivingHandlerSecuritySupport;

import java.util.UUID;
class ListGivingFundsHandlerSecurityTest extends GivingHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ListGivingFundsHandler.class;
    }
    protected Object input(UUID actor) {
        return new ListGivingFundsQuery(false, null, 25, 0);
    }

}
