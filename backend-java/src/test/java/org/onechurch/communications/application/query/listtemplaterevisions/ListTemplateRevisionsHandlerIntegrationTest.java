package org.onechurch.communications.application.query.listtemplaterevisions;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.CommunicationsIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class ListTemplateRevisionsHandlerIntegrationTest extends CommunicationsIntegrationTestSupport {
    @Autowired private ListTemplateRevisionsHandler handler;
    @Test
    void directQueryCallsRequireLiveReadPermission() {
        ListTemplateRevisionsQuery query = new ListTemplateRevisionsQuery(UUID.randomUUID(), 25, 0);
        assertIdentityFailure(() -> handler.handle(query), IdentityException.Kind.UNAUTHENTICATED);
        asActor(admin, () -> {
            db.deleteFrom(ROLE_PERMISSIONS)
                .where(ROLE_PERMISSIONS.PERMISSION_ID.in(db.select(PERMISSIONS.ID)
                    .from(PERMISSIONS)
                    .where(PERMISSIONS.CODE.in("communications.templates.read", "communications.templates.manage"))))
                .execute();
            assertIdentityFailure(() -> handler.handle(query), IdentityException.Kind.FORBIDDEN);
            return null;
        });
    }
}
