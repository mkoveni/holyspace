package org.onechurch.events.application.query.listeventvenues;
import org.onechurch.shared.infrastructure.EventHandlerSecuritySupport;

import java.util.UUID;
class ListEventVenuesHandlerSecurityTest extends EventHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ListEventVenuesHandler.class;
    }
    protected Object input(UUID actor) {
        return new ListEventVenuesQuery(null, false, 0, 0);
    }
}
