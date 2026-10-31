package org.onechurch.identityaccess.infrastructure.persistence;

import static org.jooq.impl.DSL.inline;
import static org.jooq.impl.DSL.when;
import static org.onechurch.shared.infrastructure.jooq.Tables.LOGIN_ATTEMPTS;

import org.jooq.Condition;
import org.jooq.DSLContext;
import org.onechurch.identityaccess.application.contract.LoginAttemptWriter;
import org.springframework.stereotype.Repository;

import java.time.Instant;
import java.time.OffsetDateTime;
import java.time.ZoneOffset;

@Repository
public class JooqLoginAttemptWriter implements LoginAttemptWriter {

    private final DSLContext db;

    public JooqLoginAttemptWriter(DSLContext db) {
        this.db = db;
    }

    @Override
    public void lockLogin(String identifierHash) {
        PostgresTransactionLocks.lockLogin(db, identifierHash);
    }

    @Override
    public boolean throttled(String key, Instant now) {
        return db.fetchExists(db.selectOne()
            .from(LOGIN_ATTEMPTS)
            .where(LOGIN_ATTEMPTS.IDENTIFIER_HASH.eq(key)
                .and(LOGIN_ATTEMPTS.FAILURES.ge(5))
                .and(LOGIN_ATTEMPTS.RESETS_AT.gt(time(now)))));
    }

    @Override
    public void failed(String key, Instant now) {
        Condition expired = LOGIN_ATTEMPTS.RESETS_AT.le(time(now));
        OffsetDateTime resetsAt = time(now.plusSeconds(60));
        db.insertInto(LOGIN_ATTEMPTS)
            .set(LOGIN_ATTEMPTS.IDENTIFIER_HASH, key)
            .set(LOGIN_ATTEMPTS.FAILURES, 1)
            .set(LOGIN_ATTEMPTS.RESETS_AT, resetsAt)
            .onConflict(LOGIN_ATTEMPTS.IDENTIFIER_HASH)
            .doUpdate()
            .set(LOGIN_ATTEMPTS.FAILURES, when(expired, inline(1)).otherwise(LOGIN_ATTEMPTS.FAILURES.plus(1)))
            .set(LOGIN_ATTEMPTS.RESETS_AT, when(expired, resetsAt).otherwise(LOGIN_ATTEMPTS.RESETS_AT))
            .execute();
    }

    @Override
    public void clearFailures(String key) {
        db.deleteFrom(LOGIN_ATTEMPTS).where(LOGIN_ATTEMPTS.IDENTIFIER_HASH.eq(key)).execute();
    }

    private static OffsetDateTime time(Instant instant) {
        return instant.atOffset(ZoneOffset.UTC);
    }
}
