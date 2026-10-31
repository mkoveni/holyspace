package org.onechurch.communities.application.command.schedulegroupmeetings;
import org.onechurch.shared.infrastructure.CommunityHandlerSecuritySupport;

import java.util.UUID;
class ScheduleGroupMeetingsHandlerSecurityTest extends CommunityHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ScheduleGroupMeetingsHandler.class;
    }
    protected Object input(UUID actor) {
        return new ScheduleGroupMeetingsCommand(actor, null, null, 0, null, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
