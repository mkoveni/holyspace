package org.onechurch.communities.application.command.preparegrouproster;
import org.onechurch.shared.infrastructure.CommunityHandlerSecuritySupport;

import java.util.UUID;
class PrepareGroupRosterHandlerSecurityTest extends CommunityHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return PrepareGroupRosterHandler.class;
    }
    protected Object input(UUID actor) {
        return new PrepareGroupRosterCommand(actor, null, 0);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
