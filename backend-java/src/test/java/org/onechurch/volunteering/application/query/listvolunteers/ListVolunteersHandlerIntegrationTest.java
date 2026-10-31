package org.onechurch.volunteering.application.query.listvolunteers;

import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.USER_ACCOUNTS;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.VolunteerIntegrationTestSupport;
import org.onechurch.shared.infrastructure.jooq.Tables;
import org.springframework.beans.factory.annotation.Autowired;

import java.time.OffsetDateTime;

class ListVolunteersHandlerIntegrationTest extends VolunteerIntegrationTestSupport {
    @Autowired ListVolunteersHandler handler;
    @Test
    void directQueryChecksLiveAccessAndAccountState() {
        ListVolunteersQuery query = new ListVolunteersQuery("", 25, 0);
        assertIdentityFailure(() -> handler.handle(query), IdentityException.Kind.UNAUTHENTICATED);
        assertThat(asActor(admin, () -> handler.handle(query)).total()).isEqualTo(1);
        assertIdentityFailure(() -> asActor(admin, () -> {
            db.update(USER_ACCOUNTS)
                .set(USER_ACCOUNTS.STATUS, "disabled")
                .where(USER_ACCOUNTS.ID.eq(adminId))
                .execute();
            return handler.handle(query);
        }), IdentityException.Kind.UNAUTHENTICATED);
    }
    @Test
    void directQueryRejectsRevokedAndExpiredSessions() {
        ListVolunteersQuery query = new ListVolunteersQuery("", 25, 0);
        assertIdentityFailure(() -> asActor(admin, () -> {
            db.update(Tables.ACCOUNT_SESSIONS)
                .set(Tables.ACCOUNT_SESSIONS.EXPIRES_AT, OffsetDateTime.now().minusSeconds(1))
                .execute();
            return handler.handle(query);
        }), IdentityException.Kind.UNAUTHENTICATED);
    }
}
