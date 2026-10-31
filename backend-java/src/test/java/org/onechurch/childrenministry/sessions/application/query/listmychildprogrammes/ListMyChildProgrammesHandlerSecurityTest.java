package org.onechurch.childrenministry.sessions.application.query.listmychildprogrammes;
import org.onechurch.shared.infrastructure.DonorHandlerSecuritySupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class ListMyChildProgrammesHandlerSecurityTest extends DonorHandlerSecuritySupport {
    @Autowired ListMyChildProgrammesHandler handler;
    @Override
    protected Object invoke(UUID actor) {
        handler.handle(new ListMyChildProgrammesQuery(UUID.randomUUID(), 10, 0));
        return null;
    }
}
