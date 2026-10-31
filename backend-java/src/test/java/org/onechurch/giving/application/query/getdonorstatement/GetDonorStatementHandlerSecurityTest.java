package org.onechurch.giving.application.query.getdonorstatement;
import org.onechurch.shared.infrastructure.GivingHandlerSecuritySupport;

import java.util.UUID;
class GetDonorStatementHandlerSecurityTest extends GivingHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return GetDonorStatementHandler.class;
    }
    protected Object input(UUID actor) {
        return new GetDonorStatementQuery(null, null, null);
    }

}
