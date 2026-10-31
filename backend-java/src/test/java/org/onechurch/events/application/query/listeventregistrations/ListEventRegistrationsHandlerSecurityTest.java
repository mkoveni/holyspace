package org.onechurch.events.application.query.listeventregistrations;
import org.onechurch.shared.infrastructure.EventHandlerSecuritySupport;

import java.util.UUID;
class ListEventRegistrationsHandlerSecurityTest extends EventHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ListEventRegistrationsHandler.class;
    }
    protected Object input(UUID actor) {
        return new ListEventRegistrationsQuery(null, null, 0, 0);
    }
}
