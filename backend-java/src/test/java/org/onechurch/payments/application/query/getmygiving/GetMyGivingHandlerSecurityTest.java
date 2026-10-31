package org.onechurch.payments.application.query.getmygiving;
import org.onechurch.shared.infrastructure.DonorHandlerSecuritySupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class GetMyGivingHandlerSecurityTest extends DonorHandlerSecuritySupport {
    @Autowired GetMyGivingHandler handler;
    protected Object invoke(UUID actor) {
        return handler.handle(new GetMyGivingQuery(25, 0, 0));
    }
}
