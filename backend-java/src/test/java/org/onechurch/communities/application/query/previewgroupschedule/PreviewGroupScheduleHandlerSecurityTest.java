package org.onechurch.communities.application.query.previewgroupschedule;
import org.onechurch.shared.infrastructure.CommunityHandlerSecuritySupport;

import java.util.UUID;
class PreviewGroupScheduleHandlerSecurityTest extends CommunityHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return PreviewGroupScheduleHandler.class;
    }
    protected Object input(UUID actor) {
        return new PreviewGroupScheduleQuery(null, null);
    }

}
