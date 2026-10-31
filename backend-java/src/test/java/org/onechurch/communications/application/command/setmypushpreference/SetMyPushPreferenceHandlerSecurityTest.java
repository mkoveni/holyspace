package org.onechurch.communications.application.command.setmypushpreference;
import org.onechurch.shared.infrastructure.DonorHandlerSecuritySupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class SetMyPushPreferenceHandlerSecurityTest extends DonorHandlerSecuritySupport {
    @Autowired SetMyPushPreferenceHandler handler;
    @Override
    protected Object invoke(UUID actor) {
        handler.handle(new SetMyPushPreferenceCommand("EVENTS", true, 0));
        return null;
    }
}
