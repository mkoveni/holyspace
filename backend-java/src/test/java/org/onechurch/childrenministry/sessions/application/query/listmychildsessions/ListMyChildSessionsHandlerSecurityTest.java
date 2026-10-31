package org.onechurch.childrenministry.sessions.application.query.listmychildsessions;
import org.onechurch.shared.infrastructure.DonorHandlerSecuritySupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class ListMyChildSessionsHandlerSecurityTest extends DonorHandlerSecuritySupport {
    @Autowired ListMyChildSessionsHandler handler;
    @Override
    protected Object invoke(UUID actor) {
        handler.handle(new ListMyChildSessionsQuery(UUID.randomUUID(), 10, 0));
        return null;
    }
}
