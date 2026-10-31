package org.onechurch.communities.application.command.finishgroupmeeting;
import org.onechurch.shared.infrastructure.CommunityHandlerSecuritySupport;

import java.util.UUID;
class FinishGroupMeetingHandlerSecurityTest extends CommunityHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return FinishGroupMeetingHandler.class;
    }
    protected Object input(UUID actor) {
        return new FinishGroupMeetingCommand(actor, null, 0, null, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
