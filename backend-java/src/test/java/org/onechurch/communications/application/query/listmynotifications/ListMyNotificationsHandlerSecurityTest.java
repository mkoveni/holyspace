package org.onechurch.communications.application.query.listmynotifications;
import org.onechurch.shared.infrastructure.DonorHandlerSecuritySupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class ListMyNotificationsHandlerSecurityTest extends DonorHandlerSecuritySupport {
    @Autowired ListMyNotificationsHandler handler;
    @Override
    protected Object invoke(UUID actor) {
        handler.handle(new ListMyNotificationsQuery(false, 20, 0));
        return null;
    }
}
