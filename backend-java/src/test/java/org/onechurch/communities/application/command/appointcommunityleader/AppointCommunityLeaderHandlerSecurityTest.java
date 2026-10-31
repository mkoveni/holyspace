package org.onechurch.communities.application.command.appointcommunityleader;
import org.onechurch.shared.infrastructure.CommunityHandlerSecuritySupport;

import java.util.UUID;
class AppointCommunityLeaderHandlerSecurityTest extends CommunityHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return AppointCommunityLeaderHandler.class;
    }
    protected Object input(UUID actor) {
        return new AppointCommunityLeaderCommand(actor, null, null, 0, null, null, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
