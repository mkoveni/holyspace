package org.onechurch.giving.application.query.getgiftacknowledgement;
import org.onechurch.shared.infrastructure.GivingHandlerSecuritySupport;

import java.util.UUID;
class GetGiftAcknowledgementHandlerSecurityTest extends GivingHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return GetGiftAcknowledgementHandler.class;
    }
    protected Object input(UUID actor) {
        return new GetGiftAcknowledgementQuery(null);
    }

}
