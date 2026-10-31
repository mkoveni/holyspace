package org.onechurch.giving.application.query.getpledge;
import org.onechurch.shared.infrastructure.GivingHandlerSecuritySupport;

import java.util.UUID;
class GetPledgeHandlerSecurityTest extends GivingHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return GetPledgeHandler.class;
    }
    protected Object input(UUID actor) {
        return new GetPledgeQuery(null, 25, 0);
    }

}
