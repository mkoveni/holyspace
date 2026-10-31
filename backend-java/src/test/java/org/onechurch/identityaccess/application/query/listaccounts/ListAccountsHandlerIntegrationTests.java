package org.onechurch.identityaccess.application.query.listaccounts;

import static org.assertj.core.api.Assertions.assertThat;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.IdentityIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

class ListAccountsHandlerIntegrationTests extends IdentityIntegrationTestSupport {
    @Autowired private ListAccountsHandler handler;

    @Test
    void directQueriesRequireCurrentManagementPermission() throws Exception {
        createUser("query-helper", "query-helper@church.org", "Query Helper");
        String helper = login("query-helper", PASSWORD);
        assertIdentityFailure(() -> handler.handle(new ListAccountsQuery("", 10)),
            IdentityException.Kind.UNAUTHENTICATED);
        asActor(helper, () -> {
            assertIdentityFailure(() -> handler.handle(new ListAccountsQuery("", 10)),
                IdentityException.Kind.FORBIDDEN);
            return null;
        });
        assertThat(asActor(admin, () -> handler.handle(new ListAccountsQuery("", 10)))).isNotEmpty();
    }
}
