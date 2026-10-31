package org.onechurch.giving.application.command.verifydonorlink;
import org.onechurch.shared.infrastructure.PaymentHandlerSecuritySupport;

import java.util.UUID;
class VerifyDonorLinkHandlerSecurityTest extends PaymentHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return VerifyDonorLinkHandler.class;
    }
    protected Object input(UUID actor) {
        return new VerifyDonorLinkCommand(actor, null, null, true, 0, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
