package org.onechurch.events.application.query.previeweventschedule;
import org.onechurch.shared.infrastructure.EventHandlerSecuritySupport;

import java.util.UUID;
class PreviewEventScheduleHandlerSecurityTest extends EventHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return PreviewEventScheduleHandler.class;
    }
    protected Object input(UUID actor) {
        return new PreviewEventScheduleQuery(null);
    }
}
