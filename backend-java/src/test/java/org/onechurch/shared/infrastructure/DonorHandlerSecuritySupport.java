package org.onechurch.shared.infrastructure;
import static org.onechurch.shared.infrastructure.jooq.Tables.ACCOUNT_SESSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;

import java.util.UUID;
public abstract class DonorHandlerSecuritySupport extends PaymentIntegrationTestSupport {
    protected abstract Object invoke(UUID actor);
    @Test
    void rejectsAnonymousCaller() {
        assertIdentityFailure(() -> invoke(adminId), IdentityException.Kind.UNAUTHENTICATED);
    }
    @Test
    void rejectsRevokedSessionOnDirectCalls() {
        asActor(admin, () -> {
            db.deleteFrom(ACCOUNT_SESSIONS).execute();
            assertIdentityFailure(() -> invoke(adminId), IdentityException.Kind.UNAUTHENTICATED);
            return null;
        });
    }
    @Test
    void rejectsExpiredSessionOnDirectCalls() {
        asActor(admin, () -> {
            db.update(ACCOUNT_SESSIONS)
                .set(ACCOUNT_SESSIONS.EXPIRES_AT, java.time.OffsetDateTime.now().minusSeconds(1))
                .execute();
            assertIdentityFailure(() -> invoke(adminId), IdentityException.Kind.UNAUTHENTICATED);
            return null;
        });
    }
}
