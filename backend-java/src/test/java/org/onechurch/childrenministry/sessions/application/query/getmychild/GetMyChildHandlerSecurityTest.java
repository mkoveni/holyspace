package org.onechurch.childrenministry.sessions.application.query.getmychild;
import org.onechurch.shared.infrastructure.DonorHandlerSecuritySupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class GetMyChildHandlerSecurityTest extends DonorHandlerSecuritySupport {
    @Autowired GetMyChildHandler handler;
    @Override
    protected Object invoke(UUID actor) {
        handler.handle(new GetMyChildQuery(UUID.randomUUID()));
        return null;
    }
}
