package org.onechurch.people.application.query.getperson;

import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.people.application.PersonView;
import org.onechurch.shared.infrastructure.PeopleIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;

class GetPersonHandlerTest extends PeopleIntegrationTestSupport {
    @Autowired private GetPersonHandler handler;
    @Test
    void readsRequirePeoplePermissionAndObserveRevocationWithoutAnHttpController() throws Exception {
        UUID id = person("Solly");
        assertIdentityFailure(() -> handler.handle(new GetPersonQuery(id, null)),
            IdentityException.Kind.UNAUTHENTICATED);
        String reader = readerToken();
        asActor(reader, () -> {
            PersonView result = handler.handle(new GetPersonQuery(id, null));
            assertThat(result.id()).isEqualTo(id);
            db.deleteFrom(ROLE_PERMISSIONS).where(ROLE_PERMISSIONS.PERMISSION_ID.eq(PEOPLE_READ)).execute();
            assertIdentityFailure(() -> handler.handle(new GetPersonQuery(id, null)), IdentityException.Kind.FORBIDDEN);
            return null;
        });
    }
}
