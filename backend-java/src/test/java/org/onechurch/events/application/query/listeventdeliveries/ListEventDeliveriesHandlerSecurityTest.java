package org.onechurch.events.application.query.listeventdeliveries;
import org.onechurch.shared.infrastructure.EventDayHandlerSecuritySupport;

import java.util.UUID;
class ListEventDeliveriesHandlerSecurityTest extends EventDayHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ListEventDeliveriesHandler.class;
    }
    protected Object input(UUID actor) {
        return new ListEventDeliveriesQuery(null, 0, 0);
    }
}
