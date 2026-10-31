package org.onechurch.giving.application.query.getgivingtotals;
import org.onechurch.shared.infrastructure.GivingHandlerSecuritySupport;

import java.util.UUID;
class GetGivingTotalsHandlerSecurityTest extends GivingHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return GetGivingTotalsHandler.class;
    }
    protected Object input(UUID actor) {
        return new GetGivingTotalsQuery(null);
    }

}
