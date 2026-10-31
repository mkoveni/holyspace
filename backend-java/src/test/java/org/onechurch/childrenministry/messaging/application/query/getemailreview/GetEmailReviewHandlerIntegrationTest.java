package org.onechurch.childrenministry.messaging.application.query.getemailreview;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.ChildrenIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class GetEmailReviewHandlerIntegrationTest extends ChildrenIntegrationTestSupport {
    @Autowired private GetEmailReviewHandler handler;
    @Test
    void directCallsRequireCurrentTrustedPermissions() {
        GetEmailReviewQuery request = new GetEmailReviewQuery(UUID.randomUUID());
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
