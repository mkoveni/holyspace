package org.onechurch.communications.application.command.publishtemplate;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.CommunicationsIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class PublishTemplateHandlerIntegrationTest extends CommunicationsIntegrationTestSupport {
    @Autowired private PublishTemplateHandler handler;
    @Test
    void directCallsRejectAnonymousSpoofedAndRevokedPermissions() {
        PublishTemplateCommand command = new PublishTemplateCommand(adminId, UUID.randomUUID(), 0);
        assertIdentityFailure(() -> handler.handle(command), IdentityException.Kind.UNAUTHENTICATED);
        asActor(admin, () -> {
            assertIdentityFailure(
                () -> handler.handle(new PublishTemplateCommand(UUID.randomUUID(), UUID.randomUUID(), 0)),
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
