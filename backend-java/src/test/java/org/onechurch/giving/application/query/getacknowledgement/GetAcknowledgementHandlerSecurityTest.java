package org.onechurch.giving.application.query.getacknowledgement;
import org.onechurch.shared.infrastructure.GivingHandlerSecuritySupport;

import java.util.UUID;
class GetAcknowledgementHandlerSecurityTest extends GivingHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return GetAcknowledgementHandler.class;
    }
    protected Object input(UUID actor) {
        return new GetAcknowledgementQuery(null);
    }

}
