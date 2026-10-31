package org.onechurch.people.application.command.savemycontact;
import org.onechurch.shared.infrastructure.DonorHandlerSecuritySupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class SaveMyContactHandlerSecurityTest extends DonorHandlerSecuritySupport {
    @Autowired SaveMyContactHandler handler;
    protected Object invoke(UUID actor) {
        return handler.handle(new SaveMyContactCommand(actor, null, null, null, 0));
    }
}
