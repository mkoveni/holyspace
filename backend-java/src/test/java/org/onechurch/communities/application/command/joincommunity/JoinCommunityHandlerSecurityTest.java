package org.onechurch.communities.application.command.joincommunity;
import org.onechurch.shared.infrastructure.CommunityHandlerSecuritySupport;

import java.util.UUID;
class JoinCommunityHandlerSecurityTest extends CommunityHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return JoinCommunityHandler.class;
    }
    protected Object input(UUID actor) {
        return new JoinCommunityCommand(actor, null, null, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
