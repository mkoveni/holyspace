package org.onechurch.communications.application.command.archivetemplate;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.CommunicationsIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class ArchiveTemplateHandlerIntegrationTest extends CommunicationsIntegrationTestSupport {
    @Autowired private ArchiveTemplateHandler handler;
    @Test
    void directCallsRejectAnonymousSpoofedAndRevokedPermissions() {
        ArchiveTemplateCommand command = new ArchiveTemplateCommand(adminId, UUID.randomUUID(), 0);
        assertIdentityFailure(() -> handler.handle(command), IdentityException.Kind.UNAUTHENTICATED);
        asActor(admin, () -> {
            assertIdentityFailure(
                () -> handler.handle(new ArchiveTemplateCommand(UUID.randomUUID(), UUID.randomUUID(), 0)),
                IdentityException.Kind.FORBIDDEN);
            db.deleteFrom(ROLE_PERMISSIONS)
                .where(ROLE_PERMISSIONS.PERMISSION_ID.in(db.select(PERMISSIONS.ID)
                    .from(PERMISSIONS)
                    .where(PERMISSIONS.CODE.eq("communications.templates.manage"))))
                .execute();
            assertIdentityFailure(() -> handler.handle(command), IdentityException.Kind.FORBIDDEN);
            return null;
        });
    }
}
