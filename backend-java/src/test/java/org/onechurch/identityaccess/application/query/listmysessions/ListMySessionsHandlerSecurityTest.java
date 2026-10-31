package org.onechurch.identityaccess.application.query.listmysessions;
import org.onechurch.shared.infrastructure.DonorHandlerSecuritySupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class ListMySessionsHandlerSecurityTest extends DonorHandlerSecuritySupport {
    @Autowired ListMySessionsHandler handler;
    protected Object invoke(UUID actor) {
        return handler.handle(new ListMySessionsQuery(25, 0));
    }
}
