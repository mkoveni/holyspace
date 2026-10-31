package org.onechurch.shared.infrastructure;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.BeforeEach;

import java.util.List;
import java.util.UUID;
public abstract class EventDayHandlerSecuritySupport extends EventHandlerSecuritySupport {
    @BeforeEach
    void dayPermissions() {
        for (String code : List.of("events.attendance.read", "events.attendance.manage",
            "events.communications.send")) {
            UUID id = UUID.randomUUID();
            db.insertInto(PERMISSIONS).set(PERMISSIONS.ID, id).set(PERMISSIONS.CODE, code).execute();
            db.insertInto(ROLE_PERMISSIONS)
                .set(ROLE_PERMISSIONS.ROLE_ID, ADMIN_ROLE)
                .set(ROLE_PERMISSIONS.PERMISSION_ID, id)
                .execute();
        }
    }
}
