package org.onechurch.volunteering.application.command.respondmyvolunteerinvitation;
import org.onechurch.shared.infrastructure.DonorHandlerSecuritySupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class RespondMyVolunteerInvitationHandlerSecurityTest extends DonorHandlerSecuritySupport {
    @Autowired RespondMyVolunteerInvitationHandler handler;
    @Override
    protected Object invoke(UUID actor) {
        handler.handle(new RespondMyVolunteerInvitationCommand(UUID.randomUUID(), 0, "ACCEPTED"));
        return null;
    }
}
