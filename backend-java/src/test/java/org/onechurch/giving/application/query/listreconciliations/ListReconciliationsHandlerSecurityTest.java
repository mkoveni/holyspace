package org.onechurch.giving.application.query.listreconciliations;
import org.onechurch.shared.infrastructure.GivingHandlerSecuritySupport;

import java.util.UUID;
class ListReconciliationsHandlerSecurityTest extends GivingHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ListReconciliationsHandler.class;
    }
    protected Object input(UUID actor) {
        return new ListReconciliationsQuery(25, 0);
    }

}
