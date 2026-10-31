package org.onechurch.payments.application.command.managesubscription;
import org.onechurch.shared.infrastructure.PaymentHandlerSecuritySupport;

import java.util.UUID;
class ManageSubscriptionHandlerSecurityTest extends PaymentHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ManageSubscriptionHandler.class;
    }
    protected Object input(UUID actor) {
        return new ManageSubscriptionCommand(actor, null, 0, false);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
