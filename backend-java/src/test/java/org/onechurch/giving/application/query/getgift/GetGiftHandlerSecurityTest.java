package org.onechurch.giving.application.query.getgift;
import org.onechurch.shared.infrastructure.GivingHandlerSecuritySupport;

import java.util.UUID;
class GetGiftHandlerSecurityTest extends GivingHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return GetGiftHandler.class;
    }
    protected Object input(UUID actor) {
        return new GetGiftQuery(null);
    }

}
