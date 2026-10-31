package org.onechurch.identityaccess.application.command.verifymemberlink;
import org.onechurch.shared.infrastructure.PaymentHandlerSecuritySupport;

import java.util.UUID;
class VerifyMemberLinkHandlerSecurityTest extends PaymentHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return VerifyMemberLinkHandler.class;
    }
    protected Object input(UUID actor) {
        return new VerifyMemberLinkCommand(actor, null, null, true, 0, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
