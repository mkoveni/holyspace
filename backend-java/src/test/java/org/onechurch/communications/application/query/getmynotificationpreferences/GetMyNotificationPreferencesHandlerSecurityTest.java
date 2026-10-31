package org.onechurch.communications.application.query.getmynotificationpreferences;
import org.onechurch.shared.infrastructure.DonorHandlerSecuritySupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class GetMyNotificationPreferencesHandlerSecurityTest extends DonorHandlerSecuritySupport {
    @Autowired GetMyNotificationPreferencesHandler handler;
    @Override
    protected Object invoke(UUID actor) {
        handler.handle(new GetMyNotificationPreferencesQuery());
        return null;
    }
}
