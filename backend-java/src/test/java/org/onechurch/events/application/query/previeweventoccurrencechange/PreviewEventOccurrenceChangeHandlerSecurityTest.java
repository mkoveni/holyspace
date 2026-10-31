package org.onechurch.events.application.query.previeweventoccurrencechange;
import org.onechurch.shared.infrastructure.EventHandlerSecuritySupport;

import java.util.UUID;
class PreviewEventOccurrenceChangeHandlerSecurityTest extends EventHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return PreviewEventOccurrenceChangeHandler.class;
    }
    protected Object input(UUID actor) {
        return new PreviewEventOccurrenceChangeQuery(null);
    }
}
