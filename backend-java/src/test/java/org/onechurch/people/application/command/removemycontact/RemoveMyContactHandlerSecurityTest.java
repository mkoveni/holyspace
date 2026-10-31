package org.onechurch.people.application.command.removemycontact;
import org.onechurch.shared.infrastructure.DonorHandlerSecuritySupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class RemoveMyContactHandlerSecurityTest extends DonorHandlerSecuritySupport {
    @Autowired RemoveMyContactHandler handler;
    protected Object invoke(UUID actor) {
        handler.handle(new RemoveMyContactCommand(actor, null, 0));
        return null;
    }
}
