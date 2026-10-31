package org.onechurch.payments.application.command.preparemobilecheckout;
import org.onechurch.shared.infrastructure.DonorHandlerSecuritySupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class PrepareMobileCheckoutHandlerSecurityTest extends DonorHandlerSecuritySupport {
    @Autowired PrepareMobileCheckoutHandler handler;
    protected Object invoke(UUID actor) {
        return handler.handle(new PrepareMobileCheckoutCommand(actor, null));
    }
}
