package org.onechurch.childrenministry.sessions.application.command.issuemychildpickuplabel;
import org.onechurch.shared.infrastructure.DonorHandlerSecuritySupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class IssueMyChildPickupLabelHandlerSecurityTest extends DonorHandlerSecuritySupport {
    @Autowired IssueMyChildPickupLabelHandler handler;
    @Override
    protected Object invoke(UUID actor) {
        handler.handle(new IssueMyChildPickupLabelCommand(UUID.randomUUID(), UUID.randomUUID(), 0, true));
        return null;
    }
}
