package org.onechurch.childrenministry.safeguarding.application.query.getchildreadiness;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.ChildrenIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.List;
import java.util.UUID;
class GetChildReadinessHandlerIntegrationTest extends ChildrenIntegrationTestSupport {
    @Autowired private GetChildReadinessHandler handler;
    @Test
    void directQueriesRecheckTheAuthenticatedCallersLivePermissions() {
        GetChildReadinessQuery query = new GetChildReadinessQuery(programme, child);
        assertIdentityFailure(() -> handler.handle(query), IdentityException.Kind.UNAUTHENTICATED);
        asActor(admin, () -> {
            List<UUID> permissions = db.select(PERMISSIONS.ID)
                .from(PERMISSIONS)
                .where(PERMISSIONS.CODE.in("children.read", "children.manage"))
                .fetch(PERMISSIONS.ID);
            db.deleteFrom(ROLE_PERMISSIONS).where(ROLE_PERMISSIONS.PERMISSION_ID.in(permissions)).execute();
            assertIdentityFailure(() -> handler.handle(query), IdentityException.Kind.FORBIDDEN);
            return null;
        });
    }
}
