package org.onechurch.giving.application.query.listgifts;
import org.onechurch.shared.infrastructure.GivingHandlerSecuritySupport;

import java.util.UUID;
class ListGiftsHandlerSecurityTest extends GivingHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ListGiftsHandler.class;
    }
    protected Object input(UUID actor) {
        return new ListGiftsQuery(null);
    }

}
