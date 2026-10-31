package org.onechurch.communities.application.command.createcommunity;
import org.onechurch.shared.infrastructure.CommunityHandlerSecuritySupport;

import java.util.UUID;
class CreateCommunityHandlerSecurityTest extends CommunityHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return CreateCommunityHandler.class;
    }
    protected Object input(UUID actor) {
        return new CreateCommunityCommand(actor, null, null, null, null, null, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
