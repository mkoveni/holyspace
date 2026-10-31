package org.onechurch.communities.application.query.getmycommunity;
import org.onechurch.shared.infrastructure.DonorHandlerSecuritySupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class GetMyCommunityHandlerSecurityTest extends DonorHandlerSecuritySupport {
    @Autowired GetMyCommunityHandler handler;
    protected Object invoke(UUID actor) {
        return handler.handle(new GetMyCommunityQuery(UUID.randomUUID(), 25, 0));
    }
}
