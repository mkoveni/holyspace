package org.onechurch.childrenministry.safeguarding.application.query.getsafeguardingfollowup;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.ChildrenIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class GetSafeguardingFollowUpHandlerIntegrationTest extends ChildrenIntegrationTestSupport {
    @Autowired private GetSafeguardingFollowUpHandler handler;
    @Test
    void directCallsRequireTrustedCurrentPermissions() {
        GetSafeguardingFollowUpQuery request = new GetSafeguardingFollowUpQuery(null, "", "ALL", "ALL", 30, 25, 0);
        assertIdentityFailure(() -> handler.handle(request), IdentityException.Kind.UNAUTHENTICATED);
        asActor(admin, () -> {
            UUID revoked = db.select(PERMISSIONS.ID)
                .from(PERMISSIONS)
                .where(PERMISSIONS.CODE.eq("children.safeguarding"))
                .fetchOne(PERMISSIONS.ID);
            db.deleteFrom(ROLE_PERMISSIONS).where(ROLE_PERMISSIONS.PERMISSION_ID.eq(revoked)).execute();
            assertIdentityFailure(() -> handler.handle(request), IdentityException.Kind.FORBIDDEN);
            return null;
        });
    }
}
