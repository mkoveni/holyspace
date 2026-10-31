package org.onechurch.childrenministry.sessions.application.query.listmychildren;
import org.onechurch.shared.infrastructure.DonorHandlerSecuritySupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class ListMyChildrenHandlerSecurityTest extends DonorHandlerSecuritySupport {
    @Autowired ListMyChildrenHandler handler;
    @Override
    protected Object invoke(UUID actor) {
        handler.handle(new ListMyChildrenQuery(25, 0));
        return null;
    }
}
