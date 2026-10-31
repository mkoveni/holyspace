package org.onechurch.people.application.query.getmyprofile;
import org.onechurch.shared.infrastructure.DonorHandlerSecuritySupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class GetMyProfileHandlerSecurityTest extends DonorHandlerSecuritySupport {
    @Autowired GetMyProfileHandler handler;
    protected Object invoke(UUID actor) {
        return handler.handle(new GetMyProfileQuery());
    }
}
