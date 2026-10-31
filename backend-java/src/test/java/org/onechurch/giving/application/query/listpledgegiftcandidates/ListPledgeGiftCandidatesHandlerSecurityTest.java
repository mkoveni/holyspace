package org.onechurch.giving.application.query.listpledgegiftcandidates;
import org.onechurch.shared.infrastructure.GivingHandlerSecuritySupport;

import java.util.UUID;
class ListPledgeGiftCandidatesHandlerSecurityTest extends GivingHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ListPledgeGiftCandidatesHandler.class;
    }
    protected Object input(UUID actor) {
        return new ListPledgeGiftCandidatesQuery(null, 25, 0);
    }

}
