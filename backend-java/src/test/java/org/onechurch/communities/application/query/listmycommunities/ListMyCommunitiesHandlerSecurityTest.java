package org.onechurch.communities.application.query.listmycommunities;
import org.onechurch.shared.infrastructure.DonorHandlerSecuritySupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class ListMyCommunitiesHandlerSecurityTest extends DonorHandlerSecuritySupport {
    @Autowired ListMyCommunitiesHandler handler;
    protected Object invoke(UUID actor) {
        return handler.handle(new ListMyCommunitiesQuery(null, 25, 0));
    }
}
