package org.onechurch.giving.application.command.recordgift;
import org.onechurch.shared.infrastructure.GivingHandlerSecuritySupport;

import java.util.UUID;
class RecordGiftHandlerSecurityTest extends GivingHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return RecordGiftHandler.class;
    }
    protected Object input(UUID actor) {
        return new RecordGiftCommand(actor, null, null, 0, null, null, null, null, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
