package org.onechurch.childrenministry.messaging.application.query.listguardiancommunications;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.ChildrenIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;
class ListGuardianCommunicationsHandlerIntegrationTest extends ChildrenIntegrationTestSupport {
    @Autowired private ListGuardianCommunicationsHandler handler;
    @Test
    void directCallsRequireTrustedCurrentPermissions() {
        ListGuardianCommunicationsQuery request = new ListGuardianCommunicationsQuery(null, "DRAFT", 25, 0);
        assertIdentityFailure(() -> handler.handle(request), IdentityException.Kind.UNAUTHENTICATED);
        asActor(admin, () -> {
            db.deleteFrom(ROLE_PERMISSIONS)
                .where(ROLE_PERMISSIONS.PERMISSION_ID.in(
                    db.select(PERMISSIONS.ID).from(PERMISSIONS).where(PERMISSIONS.CODE.in("children.safeguarding"))))
                .execute();
            assertIdentityFailure(() -> handler.handle(request), IdentityException.Kind.FORBIDDEN);
            return null;
        });
    }
}
