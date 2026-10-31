package org.onechurch.communications.application.query.getmynotification;
import org.onechurch.shared.infrastructure.DonorHandlerSecuritySupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class GetMyNotificationHandlerSecurityTest extends DonorHandlerSecuritySupport {
    @Autowired GetMyNotificationHandler handler;
    @Override
    protected Object invoke(UUID actor) {
        handler.handle(new GetMyNotificationQuery(UUID.randomUUID()));
        return null;
    }
}
