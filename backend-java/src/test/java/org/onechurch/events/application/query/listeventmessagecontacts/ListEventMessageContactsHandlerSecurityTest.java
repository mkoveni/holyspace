package org.onechurch.events.application.query.listeventmessagecontacts;
import org.onechurch.shared.infrastructure.EventDayHandlerSecuritySupport;

import java.util.UUID;
class ListEventMessageContactsHandlerSecurityTest extends EventDayHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ListEventMessageContactsHandler.class;
    }
    protected Object input(UUID actor) {
        return new ListEventMessageContactsQuery(null);
    }
}
