package org.onechurch.volunteering.application.query.getmyvolunteering;
import org.onechurch.shared.infrastructure.DonorHandlerSecuritySupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class GetMyVolunteeringHandlerSecurityTest extends DonorHandlerSecuritySupport {
    @Autowired GetMyVolunteeringHandler handler;
    @Override
    protected Object invoke(UUID actor) {
        handler.handle(new GetMyVolunteeringQuery(25, 0));
        return null;
    }
}
