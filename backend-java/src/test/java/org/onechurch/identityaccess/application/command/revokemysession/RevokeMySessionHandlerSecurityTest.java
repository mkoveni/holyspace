package org.onechurch.identityaccess.application.command.revokemysession;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.DonorHandlerSecuritySupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class RevokeMySessionHandlerSecurityTest extends DonorHandlerSecuritySupport {
    @Autowired RevokeMySessionHandler handler;
    protected Object invoke(UUID actor) {
        handler.handle(new RevokeMySessionCommand(actor, UUID.randomUUID()));
        return null;
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        asActor(admin, () -> {
            assertIdentityFailure(() -> invoke(UUID.randomUUID()), IdentityException.Kind.FORBIDDEN);
            return null;
        });
    }
}
