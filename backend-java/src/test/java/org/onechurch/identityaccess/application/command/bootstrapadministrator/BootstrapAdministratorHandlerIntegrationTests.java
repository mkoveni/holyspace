package org.onechurch.identityaccess.application.command.bootstrapadministrator;

import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;
import static org.onechurch.shared.infrastructure.jooq.Tables.IDENTITY_ACCESS_AUDIT;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.IdentityIntegrationTestSupport;

class BootstrapAdministratorHandlerIntegrationTests extends IdentityIntegrationTestSupport {

    @Test
    void bootstrapCannotRunAgainAndAuditNeverStoresSecrets() {
        assertThatThrownBy(() -> bootstrap
            .handle(new BootstrapAdministratorCommand("second", "second@church.org", "Second", PASSWORD)))
            .isInstanceOf(IdentityException.class)
            .hasMessageContaining("before the first account");
        String audit = db.selectFrom(IDENTITY_ACCESS_AUDIT).fetch().formatJSON();
        assertThat(audit).contains("administrator.bootstrapped").doesNotContain(PASSWORD, admin, "$2");
    }
}
