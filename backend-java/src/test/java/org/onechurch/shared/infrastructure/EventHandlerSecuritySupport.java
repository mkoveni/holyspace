package org.onechurch.shared.infrastructure;
import static org.onechurch.shared.infrastructure.jooq.Tables.ACCOUNT_SESSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.context.ApplicationContext;

import java.lang.reflect.InvocationTargetException;
import java.util.UUID;
public abstract class EventHandlerSecuritySupport extends EventsIntegrationTestSupport {
    @Autowired private ApplicationContext context;
    protected abstract Class<?> handlerType();
    protected abstract Object input(UUID actor);
    private Object invoke(UUID actor) {
        try {
            Object handler = context.getBean(handlerType());
            return handlerType().getMethod("handle", input(actor).getClass()).invoke(handler, input(actor));
        } catch (InvocationTargetException e) {
            if (e.getCause() instanceof RuntimeException failure) {
                throw failure;
            }
            throw new IllegalStateException(e.getCause());
        } catch (ReflectiveOperationException e) {
            throw new IllegalStateException(e);
        }
    }
    @Test
    void rejectsAnonymousAndLivePermissionRevocation() {
        assertIdentityFailure(() -> invoke(adminId), IdentityException.Kind.UNAUTHENTICATED);
        asActor(admin, () -> {
            db.deleteFrom(ROLE_PERMISSIONS).where(ROLE_PERMISSIONS.ROLE_ID.eq(ADMIN_ROLE)).execute();
            assertIdentityFailure(() -> invoke(adminId), IdentityException.Kind.FORBIDDEN);
            return null;
        });
    }
    @Test
    void rejectsRevokedSession() {
        asActor(admin, () -> {
            db.deleteFrom(ACCOUNT_SESSIONS).execute();
            assertIdentityFailure(() -> invoke(adminId), IdentityException.Kind.UNAUTHENTICATED);
            return null;
        });
    }
    @Test
    void rejectsExpiredSession() {
        asActor(admin, () -> {
            db.update(ACCOUNT_SESSIONS)
                .set(ACCOUNT_SESSIONS.EXPIRES_AT, java.time.OffsetDateTime.now().minusSeconds(1))
                .execute();
            assertIdentityFailure(() -> invoke(adminId), IdentityException.Kind.UNAUTHENTICATED);
            return null;
        });
    }
    protected void assertSpoofedActor() {
        asActor(admin, () -> {
            assertIdentityFailure(() -> invoke(UUID.randomUUID()), IdentityException.Kind.FORBIDDEN);
            return null;
        });
    }
}
