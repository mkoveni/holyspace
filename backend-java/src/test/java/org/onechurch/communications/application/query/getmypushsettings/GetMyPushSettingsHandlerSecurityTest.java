package org.onechurch.communications.application.query.getmypushsettings;
import org.onechurch.shared.infrastructure.DonorHandlerSecuritySupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class GetMyPushSettingsHandlerSecurityTest extends DonorHandlerSecuritySupport {
    @Autowired GetMyPushSettingsHandler handler;
    @Override
    protected Object invoke(UUID actor) {
        handler.handle(new GetMyPushSettingsQuery());
        return null;
    }
}
