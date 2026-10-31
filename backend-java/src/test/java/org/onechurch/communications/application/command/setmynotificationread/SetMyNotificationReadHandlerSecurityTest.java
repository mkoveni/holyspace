package org.onechurch.communications.application.command.setmynotificationread;
import org.onechurch.shared.infrastructure.DonorHandlerSecuritySupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class SetMyNotificationReadHandlerSecurityTest extends DonorHandlerSecuritySupport {
    @Autowired SetMyNotificationReadHandler handler;
    @Override
    protected Object invoke(UUID actor) {
        handler.handle(new SetMyNotificationReadCommand(UUID.randomUUID(), true));
        return null;
    }
}
