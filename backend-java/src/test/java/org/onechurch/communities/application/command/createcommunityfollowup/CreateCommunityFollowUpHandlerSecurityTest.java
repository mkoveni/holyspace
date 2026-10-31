package org.onechurch.communities.application.command.createcommunityfollowup;
import org.onechurch.shared.infrastructure.CommunityHandlerSecuritySupport;

import java.util.UUID;
class CreateCommunityFollowUpHandlerSecurityTest extends CommunityHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return CreateCommunityFollowUpHandler.class;
    }
    protected Object input(UUID actor) {
        return new CreateCommunityFollowUpCommand(actor, null, null, null, null, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
