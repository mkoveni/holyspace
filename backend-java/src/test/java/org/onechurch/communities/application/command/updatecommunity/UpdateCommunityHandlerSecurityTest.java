package org.onechurch.communities.application.command.updatecommunity;
import org.onechurch.shared.infrastructure.CommunityHandlerSecuritySupport;

import java.util.UUID;
class UpdateCommunityHandlerSecurityTest extends CommunityHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return UpdateCommunityHandler.class;
    }
    protected Object input(UUID actor) {
        return new UpdateCommunityCommand(actor, null, 0, null, null, null, null, false, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
