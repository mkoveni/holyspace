package org.onechurch.communities.application.command.leavecommunity;
import org.onechurch.shared.infrastructure.CommunityHandlerSecuritySupport;

import java.util.UUID;
class LeaveCommunityHandlerSecurityTest extends CommunityHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return LeaveCommunityHandler.class;
    }
    protected Object input(UUID actor) {
        return new LeaveCommunityCommand(actor, null, 0, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
