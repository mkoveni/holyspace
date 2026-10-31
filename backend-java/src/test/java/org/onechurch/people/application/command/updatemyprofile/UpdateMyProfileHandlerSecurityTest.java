package org.onechurch.people.application.command.updatemyprofile;
import org.onechurch.shared.infrastructure.DonorHandlerSecuritySupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class UpdateMyProfileHandlerSecurityTest extends DonorHandlerSecuritySupport {
    @Autowired UpdateMyProfileHandler handler;
    protected Object invoke(UUID actor) {
        handler.handle(new UpdateMyProfileCommand(actor, null, null, null, null, 0));
        return null;
    }
}
