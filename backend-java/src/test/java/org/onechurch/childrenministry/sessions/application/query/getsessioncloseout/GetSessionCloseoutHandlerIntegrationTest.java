package org.onechurch.childrenministry.sessions.application.query.getsessioncloseout;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.ChildrenIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class GetSessionCloseoutHandlerIntegrationTest extends ChildrenIntegrationTestSupport {
    @Autowired private GetSessionCloseoutHandler handler;
    @Test
    void directCloseoutQueriesRejectAnonymousSpoofedAndRevokedCallers() {
        GetSessionCloseoutQuery query = new GetSessionCloseoutQuery(adminId, session);
        assertIdentityFailure(() -> handler.handle(query), IdentityException.Kind.UNAUTHENTICATED);
        asActor(admin, () -> {
            assertIdentityFailure(() -> handler.handle(new GetSessionCloseoutQuery(UUID.randomUUID(), session)),
                IdentityException.Kind.FORBIDDEN);
            UUID permission = db.select(PERMISSIONS.ID)
                .from(PERMISSIONS)
                .where(PERMISSIONS.CODE.eq("children.manage"))
                .fetchOne(PERMISSIONS.ID);
            db.deleteFrom(ROLE_PERMISSIONS).where(ROLE_PERMISSIONS.PERMISSION_ID.eq(permission)).execute();
            assertIdentityFailure(() -> handler.handle(query), IdentityException.Kind.FORBIDDEN);
            return null;
        });
    }
}
