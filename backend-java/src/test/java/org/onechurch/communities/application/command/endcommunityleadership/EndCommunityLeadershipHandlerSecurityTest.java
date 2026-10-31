package org.onechurch.communities.application.command.endcommunityleadership;
import org.onechurch.shared.infrastructure.CommunityHandlerSecuritySupport;

import java.util.UUID;
class EndCommunityLeadershipHandlerSecurityTest extends CommunityHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return EndCommunityLeadershipHandler.class;
    }
    protected Object input(UUID actor) {
        return new EndCommunityLeadershipCommand(actor, null, 0, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
