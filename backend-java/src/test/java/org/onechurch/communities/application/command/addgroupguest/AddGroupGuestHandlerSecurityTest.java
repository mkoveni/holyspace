package org.onechurch.communities.application.command.addgroupguest;
import org.onechurch.shared.infrastructure.CommunityHandlerSecuritySupport;

import java.util.UUID;
class AddGroupGuestHandlerSecurityTest extends CommunityHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return AddGroupGuestHandler.class;
    }
    protected Object input(UUID actor) {
        return new AddGroupGuestCommand(actor, null, 0, null, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
