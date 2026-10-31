package org.onechurch.communications.application.command.createtemplate;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.CommunicationsIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class CreateTemplateHandlerIntegrationTest extends CommunicationsIntegrationTestSupport {
    @Autowired private CreateTemplateHandler handler;
    @Test
    void directCallsRejectAnonymousSpoofedAndRevokedPermissions() {
        CreateTemplateCommand command =
            new CreateTemplateCommand(adminId, "test.template", "Test", "Hello", "<p>Hello</p>", java.util.List.of());
        assertIdentityFailure(() -> handler.handle(command), IdentityException.Kind.UNAUTHENTICATED);
        asActor(admin, () -> {
            assertIdentityFailure(() -> handler.handle(new CreateTemplateCommand(UUID.randomUUID(), "test.template",
                "Test", "Hello", "<p>Hello</p>", java.util.List.of())), IdentityException.Kind.FORBIDDEN);
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
