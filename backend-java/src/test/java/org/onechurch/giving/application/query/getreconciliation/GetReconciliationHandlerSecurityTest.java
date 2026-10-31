package org.onechurch.giving.application.query.getreconciliation;
import org.onechurch.shared.infrastructure.GivingHandlerSecuritySupport;

import java.util.UUID;
class GetReconciliationHandlerSecurityTest extends GivingHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return GetReconciliationHandler.class;
    }
    protected Object input(UUID actor) {
        return new GetReconciliationQuery(null);
    }

}
