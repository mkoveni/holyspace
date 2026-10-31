package org.onechurch.communities.application.command.approvecommunitynotice;
import org.onechurch.shared.infrastructure.CommunityHandlerSecuritySupport;

import java.util.UUID;
class ApproveCommunityNoticeHandlerSecurityTest extends CommunityHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ApproveCommunityNoticeHandler.class;
    }
    protected Object input(UUID actor) {
        return new ApproveCommunityNoticeCommand(actor, null, null, null, null, null, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
