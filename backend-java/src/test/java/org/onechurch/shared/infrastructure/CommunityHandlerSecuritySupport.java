package org.onechurch.shared.infrastructure;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNITIES;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.BeforeEach;

import java.util.List;
import java.util.UUID;
public abstract class CommunityHandlerSecuritySupport extends EventDayHandlerSecuritySupport {
    @BeforeEach
    protected void resetCommunities() {
        db.truncate(COMMUNITIES).cascade().execute();
        db.truncate(org.onechurch.shared.infrastructure.jooq.Tables.COMMUNITY_REQUESTS).execute();
        for (String code : List.of("ministries.read", "ministries.manage", "ministries.lead",
            "ministries.communications.send")) {
            UUID permission = UUID.randomUUID();
            db.insertInto(PERMISSIONS).set(PERMISSIONS.ID, permission).set(PERMISSIONS.CODE, code).execute();
            db.insertInto(ROLE_PERMISSIONS)
                .set(ROLE_PERMISSIONS.ROLE_ID, ADMIN_ROLE)
                .set(ROLE_PERMISSIONS.PERMISSION_ID, permission)
                .execute();
        }
    }
}
