package org.onechurch.communications.application.query.gettemplate;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.CommunicationsIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class GetTemplateHandlerIntegrationTest extends CommunicationsIntegrationTestSupport {
    @Autowired private GetTemplateHandler handler;
    @Test
    void directQueryCallsRequireLiveReadPermission() {
        GetTemplateQuery query = new GetTemplateQuery(UUID.randomUUID());
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
