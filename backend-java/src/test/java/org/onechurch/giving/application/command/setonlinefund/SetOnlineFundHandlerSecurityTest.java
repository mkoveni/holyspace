package org.onechurch.giving.application.command.setonlinefund;
import org.onechurch.shared.infrastructure.PaymentHandlerSecuritySupport;

import java.util.UUID;
class SetOnlineFundHandlerSecurityTest extends PaymentHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return SetOnlineFundHandler.class;
    }
    protected Object input(UUID actor) {
        return new SetOnlineFundCommand(actor, null, true, 0);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
