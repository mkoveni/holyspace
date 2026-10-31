package org.onechurch.events.application.command.cancelmyevent;
import org.onechurch.shared.infrastructure.DonorHandlerSecuritySupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class CancelMyEventHandlerSecurityTest extends DonorHandlerSecuritySupport {
    @Autowired CancelMyEventHandler handler;
    protected Object invoke(UUID actor) {
        handler.handle(new CancelMyEventCommand(actor, null, 0));
        return null;
    }
}
