package org.onechurch.identityaccess.application.query.getaccount;

import static org.assertj.core.api.Assertions.assertThat;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.IdentityIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;

class GetAccountHandlerIntegrationTests extends IdentityIntegrationTestSupport {
    @Autowired private GetAccountHandler handler;

    @Test
    void allowsOwnProfileButRequiresPermissionForAnotherAccount() throws Exception {
        UUID helperId = createUser("query-helper", "query-helper@church.org", "Query Helper");
        String helper = login("query-helper", PASSWORD);
        assertIdentityFailure(() -> handler.handle(new GetAccountQuery(adminId)),
            IdentityException.Kind.UNAUTHENTICATED);
        asActor(helper, () -> {
            assertThat(handler.handle(new GetAccountQuery(helperId)).id()).isEqualTo(helperId);
            assertIdentityFailure(() -> handler.handle(new GetAccountQuery(adminId)), IdentityException.Kind.FORBIDDEN);
            return null;
        });
        assertThat(asActor(admin, () -> handler.handle(new GetAccountQuery(helperId))).id()).isEqualTo(helperId);
    }
}
