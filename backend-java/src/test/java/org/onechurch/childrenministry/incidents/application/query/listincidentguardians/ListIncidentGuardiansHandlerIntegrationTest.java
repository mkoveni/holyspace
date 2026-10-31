package org.onechurch.childrenministry.incidents.application.query.listincidentguardians;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.ChildrenIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class ListIncidentGuardiansHandlerIntegrationTest extends ChildrenIntegrationTestSupport {
    @Autowired private ListIncidentGuardiansHandler handler;
    @Test
    void directCallsRequireTrustedCurrentPermissions() {
        ListIncidentGuardiansQuery request = new ListIncidentGuardiansQuery(UUID.randomUUID());
        assertIdentityFailure(() -> handler.handle(request), IdentityException.Kind.UNAUTHENTICATED);
        asActor(admin, () -> {
            db.deleteFrom(ROLE_PERMISSIONS)
                .where(ROLE_PERMISSIONS.PERMISSION_ID.in(db.select(PERMISSIONS.ID)
                    .from(PERMISSIONS)
                    .where(PERMISSIONS.CODE.in("children.incidents.manage"))))
                .execute();
            assertIdentityFailure(() -> handler.handle(request), IdentityException.Kind.FORBIDDEN);
            return null;
        });
    }
}
