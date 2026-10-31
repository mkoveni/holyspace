package org.onechurch.communications.application.command.revokemypushdevice;
import org.onechurch.shared.infrastructure.DonorHandlerSecuritySupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class RevokeMyPushDeviceHandlerSecurityTest extends DonorHandlerSecuritySupport {
    @Autowired RevokeMyPushDeviceHandler handler;
    @Override
    protected Object invoke(UUID actor) {
        handler.handle(new RevokeMyPushDeviceCommand(UUID.randomUUID()));
        return null;
    }
}
