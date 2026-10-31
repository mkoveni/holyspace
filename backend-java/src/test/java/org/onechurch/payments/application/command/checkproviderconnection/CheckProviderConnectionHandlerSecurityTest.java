package org.onechurch.payments.application.command.checkproviderconnection;
import org.onechurch.shared.infrastructure.PaymentHandlerSecuritySupport;

import java.util.UUID;
class CheckProviderConnectionHandlerSecurityTest extends PaymentHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return CheckProviderConnectionHandler.class;
    }
    protected Object input(UUID actor) {
        return new CheckProviderConnectionCommand(actor, null, null, 0);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
