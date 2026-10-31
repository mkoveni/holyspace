package org.onechurch.events.application.command.registermyevent;
import org.onechurch.shared.infrastructure.DonorHandlerSecuritySupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class RegisterMyEventHandlerSecurityTest extends DonorHandlerSecuritySupport {
    @Autowired RegisterMyEventHandler handler;
    protected Object invoke(UUID actor) {
        handler.handle(new RegisterMyEventCommand(actor, null, null, null));
        return null;
    }
}
