package org.onechurch.events.application.query.geteventreadiness;
import org.onechurch.shared.infrastructure.EventDayHandlerSecuritySupport;

import java.util.UUID;
class GetEventReadinessHandlerSecurityTest extends EventDayHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return GetEventReadinessHandler.class;
    }
    protected Object input(UUID actor) {
        return new GetEventReadinessQuery(null);
    }

}
