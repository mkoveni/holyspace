package org.onechurch.identityaccess.application.query.authenticatesession;

import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;
import static org.onechurch.shared.infrastructure.jooq.Tables.ACCOUNT_SESSIONS;

import org.junit.jupiter.api.Test;
import org.junit.jupiter.params.ParameterizedTest;
import org.junit.jupiter.params.provider.ValueSource;
import org.onechurch.identityaccess.application.contract.AccessReader;
import org.onechurch.identityaccess.application.contract.AccountReader;
import org.onechurch.identityaccess.application.contract.SessionReader;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.IdentityIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.time.Clock;
import java.time.Instant;
import java.time.OffsetDateTime;
import java.time.ZoneOffset;

class AuthenticateSessionHandlerIntegrationTests extends IdentityIntegrationTestSupport {

    @Autowired private SessionReader sessions;
    @Autowired private AccountReader accounts;
    @Autowired private AccessReader access;

    @ParameterizedTest
    @ValueSource(longs = {-1, 0, 1})
    void sessionsAreValidOnlyBeforeTheirExpiry(long secondsUntilExpiry) {
        Instant now = Instant.parse("2026-10-25T12:00:00Z");
        db.update(ACCOUNT_SESSIONS)
            .set(ACCOUNT_SESSIONS.EXPIRES_AT, now.plusSeconds(secondsUntilExpiry).atOffset(ZoneOffset.UTC))
            .where(ACCOUNT_SESSIONS.TOKEN_HASH.eq(tokens.hash(admin)))
            .execute();
        AuthenticateSessionHandler handler =
            new AuthenticateSessionHandler(sessions, accounts, access, tokens, Clock.fixed(now, ZoneOffset.UTC));
        AuthenticateSessionQuery query = new AuthenticateSessionQuery(admin);

        if (secondsUntilExpiry > 0) {
            assertThat(handler.handle(query).account().id()).isEqualTo(adminId);
        } else {
            assertThatThrownBy(() -> handler.handle(query)).isInstanceOfSatisfying(IdentityException.class,
                error -> assertThat(error.kind()).isEqualTo(IdentityException.Kind.UNAUTHENTICATED));
        }
        assertThat(db.fetchCount(ACCOUNT_SESSIONS)).isEqualTo(1);
    }

    @Test
    void expiredSessionsAreRejectedWithoutMutatingOnReads() throws Exception {
        db.update(ACCOUNT_SESSIONS)
            .set(ACCOUNT_SESSIONS.EXPIRES_AT, OffsetDateTime.now().minusSeconds(1))
            .where(ACCOUNT_SESSIONS.TOKEN_HASH.eq(tokens.hash(admin)))
            .execute();
        assertThat(request("GET", "/api/me", admin, null).status()).isEqualTo(401);
        assertThat(db.fetchCount(ACCOUNT_SESSIONS)).isEqualTo(1);
    }
}
