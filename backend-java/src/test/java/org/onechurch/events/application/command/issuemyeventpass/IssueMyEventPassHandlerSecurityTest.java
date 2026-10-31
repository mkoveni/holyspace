package org.onechurch.events.application.command.issuemyeventpass;
import org.onechurch.shared.infrastructure.DonorHandlerSecuritySupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class IssueMyEventPassHandlerSecurityTest extends DonorHandlerSecuritySupport {
    @Autowired IssueMyEventPassHandler handler;
    protected Object invoke(UUID actor) {
        handler.handle(new IssueMyEventPassCommand(actor, null));
        return null;
    }
}
