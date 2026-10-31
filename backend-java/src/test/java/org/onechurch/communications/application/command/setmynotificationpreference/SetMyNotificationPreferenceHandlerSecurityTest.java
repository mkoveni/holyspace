package org.onechurch.communications.application.command.setmynotificationpreference;
import org.onechurch.shared.infrastructure.DonorHandlerSecuritySupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class SetMyNotificationPreferenceHandlerSecurityTest extends DonorHandlerSecuritySupport {
    @Autowired SetMyNotificationPreferenceHandler handler;
    @Override
    protected Object invoke(UUID actor) {
        handler.handle(new SetMyNotificationPreferenceCommand("EVENTS", true, 0));
        return null;
    }
}
