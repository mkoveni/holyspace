package org.onechurch.giving.application.query.listpledges;
import org.onechurch.shared.infrastructure.GivingHandlerSecuritySupport;

import java.util.UUID;
class ListPledgesHandlerSecurityTest extends GivingHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ListPledgesHandler.class;
    }
    protected Object input(UUID actor) {
        return new ListPledgesQuery(null, null, 25, 0);
    }

}
