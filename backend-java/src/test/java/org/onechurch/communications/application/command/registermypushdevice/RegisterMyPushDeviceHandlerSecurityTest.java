package org.onechurch.communications.application.command.registermypushdevice;
import org.onechurch.shared.infrastructure.DonorHandlerSecuritySupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class RegisterMyPushDeviceHandlerSecurityTest extends DonorHandlerSecuritySupport {
    @Autowired RegisterMyPushDeviceHandler handler;
    @Override
    protected Object invoke(UUID actor) {
        handler.handle(
            new RegisterMyPushDeviceCommand(UUID.randomUUID(), "secret", "token", "android", UUID.randomUUID()));
        return null;
    }
}
