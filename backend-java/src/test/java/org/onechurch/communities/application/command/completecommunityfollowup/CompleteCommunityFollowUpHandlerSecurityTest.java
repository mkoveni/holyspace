package org.onechurch.communities.application.command.completecommunityfollowup;
import org.onechurch.shared.infrastructure.CommunityHandlerSecuritySupport;

import java.util.UUID;
class CompleteCommunityFollowUpHandlerSecurityTest extends CommunityHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return CompleteCommunityFollowUpHandler.class;
    }
    protected Object input(UUID actor) {
        return new CompleteCommunityFollowUpCommand(actor, null, 0, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
