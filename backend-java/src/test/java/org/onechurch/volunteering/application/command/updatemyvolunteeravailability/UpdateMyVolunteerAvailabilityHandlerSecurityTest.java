package org.onechurch.volunteering.application.command.updatemyvolunteeravailability;
import org.onechurch.shared.infrastructure.DonorHandlerSecuritySupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.List;
import java.util.UUID;
class UpdateMyVolunteerAvailabilityHandlerSecurityTest extends DonorHandlerSecuritySupport {
    @Autowired UpdateMyVolunteerAvailabilityHandler handler;
    @Override
    protected Object invoke(UUID actor) {
        handler.handle(new UpdateMyVolunteerAvailabilityCommand(UUID.randomUUID(), 0, List.of(), List.of()));
        return null;
    }
}
