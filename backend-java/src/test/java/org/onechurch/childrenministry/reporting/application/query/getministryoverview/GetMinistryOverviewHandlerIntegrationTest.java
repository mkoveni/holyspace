package org.onechurch.childrenministry.reporting.application.query.getministryoverview;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.ChildrenIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;
class GetMinistryOverviewHandlerIntegrationTest extends ChildrenIntegrationTestSupport {
    @Autowired private GetMinistryOverviewHandler handler;
    @Test
    void directCallsRequireCurrentTrustedPermissions() {
        GetMinistryOverviewQuery request = new GetMinistryOverviewQuery();
        assertIdentityFailure(() -> handler.handle(request), IdentityException.Kind.UNAUTHENTICATED);
        asActor(admin, () -> {
            db.deleteFrom(ROLE_PERMISSIONS)
                .where(ROLE_PERMISSIONS.PERMISSION_ID.in(db.select(PERMISSIONS.ID)
                    .from(PERMISSIONS)
                    .where(PERMISSIONS.CODE.in("children.read", "children.manage", "children.safeguarding",
                        "children.incidents.manage"))))
                .execute();
            assertIdentityFailure(() -> handler.handle(request), IdentityException.Kind.FORBIDDEN);
            return null;
        });
    }
}
