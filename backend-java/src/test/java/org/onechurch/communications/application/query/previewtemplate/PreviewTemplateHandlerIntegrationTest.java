package org.onechurch.communications.application.query.previewtemplate;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.CommunicationsIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;
class PreviewTemplateHandlerIntegrationTest extends CommunicationsIntegrationTestSupport {
    @Autowired private PreviewTemplateHandler handler;
    @Test
    void directQueryCallsRequireLiveReadPermission() {
        PreviewTemplateQuery query =
            new PreviewTemplateQuery("Hello", "<p>Hello</p>", java.util.List.of(), java.util.Map.of());
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
