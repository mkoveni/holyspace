package org.onechurch.events.application.query.revieweventnotification;
import org.onechurch.shared.infrastructure.EventDayHandlerSecuritySupport;

import java.util.UUID;
class ReviewEventNotificationHandlerSecurityTest extends EventDayHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ReviewEventNotificationHandler.class;
    }
    protected Object input(UUID actor) {
        return new ReviewEventNotificationQuery(null, null, null, null);
    }
}
