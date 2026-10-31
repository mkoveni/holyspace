package org.onechurch.shared.infrastructure;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.BeforeEach;

import java.util.UUID;
public abstract class CommunicationsIntegrationTestSupport extends IdentityIntegrationTestSupport {
    @BeforeEach
    void grantTemplatePermissions() {
        for (String code : new String[]{"communications.templates.read", "communications.templates.manage"}) {
            UUID id = UUID.randomUUID();
            db.insertInto(PERMISSIONS).set(PERMISSIONS.ID, id).set(PERMISSIONS.CODE, code).execute();
            db.insertInto(ROLE_PERMISSIONS)
                .set(ROLE_PERMISSIONS.ROLE_ID, ADMIN_ROLE)
                .set(ROLE_PERMISSIONS.PERMISSION_ID, id)
                .execute();
        }
    }
}
