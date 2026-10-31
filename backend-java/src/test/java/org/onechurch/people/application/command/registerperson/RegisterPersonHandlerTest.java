package org.onechurch.people.application.command.registerperson;

import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.ACCOUNT_SESSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.PEOPLE_AUDIT;
import static org.onechurch.shared.infrastructure.jooq.Tables.PEOPLE_PERSONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.people.domain.PersonDetails;
import org.onechurch.shared.infrastructure.PeopleIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.time.OffsetDateTime;
import java.time.ZoneOffset;
import java.util.UUID;

class RegisterPersonHandlerTest extends PeopleIntegrationTestSupport {
    @Autowired private RegisterPersonHandler handler;
    private RegisterPersonCommand command(UUID actorId) {
        return new RegisterPersonCommand(actorId, new PersonDetails(null, "Solly", "Mabasa", null, null, null, null),
            null);
    }
    @Test
    void deniesAnonymousUnprivilegedAndSpoofedCallersBeforeWriting() throws Exception {
        assertIdentityFailure(() -> handler.handle(command(adminId)), IdentityException.Kind.UNAUTHENTICATED);
        String reader = readerToken();
        assertIdentityFailure(() -> asActor(reader, () -> handler.handle(command(adminId))),
            IdentityException.Kind.FORBIDDEN);
        assertIdentityFailure(() -> asActor(admin, () -> handler.handle(command(UUID.randomUUID()))),
            IdentityException.Kind.FORBIDDEN);
        assertThat(db.fetchCount(PEOPLE_PERSONS)).isZero();
        assertThat(db.fetchCount(PEOPLE_AUDIT)).isZero();
    }
    @Test
    void rechecksPermissionsAndSessionInsideAnExistingAuthenticationContext() {
        asActor(admin, () -> {
            db.deleteFrom(ROLE_PERMISSIONS).where(ROLE_PERMISSIONS.PERMISSION_ID.eq(PEOPLE_MANAGE)).execute();
            assertIdentityFailure(() -> handler.handle(command(adminId)), IdentityException.Kind.FORBIDDEN);
            db.insertInto(ROLE_PERMISSIONS)
                .set(ROLE_PERMISSIONS.ROLE_ID, ADMIN_ROLE)
                .set(ROLE_PERMISSIONS.PERMISSION_ID, PEOPLE_MANAGE)
                .execute();
            UUID id = handler.handle(command(adminId));
            assertThat(id).isNotNull();
            db.update(ACCOUNT_SESSIONS)
                .set(ACCOUNT_SESSIONS.EXPIRES_AT, OffsetDateTime.now(ZoneOffset.UTC).minusSeconds(1))
                .execute();
            assertIdentityFailure(() -> handler.handle(command(adminId)), IdentityException.Kind.UNAUTHENTICATED);
            return null;
        });
        assertThat(db.fetchCount(PEOPLE_PERSONS)).isEqualTo(1);
        assertThat(db.fetchCount(PEOPLE_AUDIT)).isEqualTo(1);
    }
}
