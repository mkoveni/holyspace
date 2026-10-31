package org.onechurch.childrenministry.messaging.application.command.retryemaildelivery;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.ChildrenIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class RetryEmailDeliveryHandlerIntegrationTest extends ChildrenIntegrationTestSupport {
    @Autowired private RetryEmailDeliveryHandler handler;
    @Test
    void directCallsRequireCurrentTrustedPermissions() {
        RetryEmailDeliveryCommand request = new RetryEmailDeliveryCommand(adminId, null, 0, null);
        assertIdentityFailure(() -> handler.handle(request), IdentityException.Kind.UNAUTHENTICATED);
        asActor(admin, () -> {
            assertIdentityFailure(() -> handler.handle(new RetryEmailDeliveryCommand(UUID.randomUUID(), null, 0, null)),
                IdentityException.Kind.FORBIDDEN);
            db.deleteFrom(ROLE_PERMISSIONS)
                .where(ROLE_PERMISSIONS.PERMISSION_ID.in(db.select(PERMISSIONS.ID)
                    .from(PERMISSIONS)
                    .where(PERMISSIONS.CODE.in("children.communications.send"))))
                .execute();
            assertIdentityFailure(() -> handler.handle(request), IdentityException.Kind.FORBIDDEN);
            return null;
        });
    }
}
