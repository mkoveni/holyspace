package org.onechurch.childrenministry.sessions.application.query.getfamilyarrivalreference;
import org.onechurch.shared.infrastructure.DonorHandlerSecuritySupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class GetFamilyArrivalReferenceHandlerSecurityTest extends DonorHandlerSecuritySupport {
    @Autowired GetFamilyArrivalReferenceHandler handler;
    @Override
    protected Object invoke(UUID actor) {
        return handler.handle(new GetFamilyArrivalReferenceQuery(UUID.randomUUID(), UUID.randomUUID()));
    }
}
