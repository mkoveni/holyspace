package org.onechurch.childrenministry.sessions.application.query.liststationsessions;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.ChildrenIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class ListStationSessionsHandlerIntegrationTest extends ChildrenIntegrationTestSupport {
    @Autowired private ListStationSessionsHandler handler;
    @Test
    void directQueriesRejectAnonymousSpoofedAndRevokedCallers() {
        ListStationSessionsQuery query = new ListStationSessionsQuery(adminId, null, null, 25, 0);
        assertIdentityFailure(() -> handler.handle(query), IdentityException.Kind.UNAUTHENTICATED);
        asActor(admin, () -> {
            assertIdentityFailure(
                () -> handler.handle(new ListStationSessionsQuery(UUID.randomUUID(), null, null, 25, 0)),
                IdentityException.Kind.FORBIDDEN);
            return null;
        });
        asActor(admin, () -> {
            UUID permission = db.select(PERMISSIONS.ID)
                .from(PERMISSIONS)
                .where(PERMISSIONS.CODE.eq("children.station"))
                .fetchOne(PERMISSIONS.ID);
            db.deleteFrom(ROLE_PERMISSIONS).where(ROLE_PERMISSIONS.PERMISSION_ID.eq(permission)).execute();
            assertIdentityFailure(() -> handler.handle(query), IdentityException.Kind.FORBIDDEN);
            return null;
        });
    }
}
