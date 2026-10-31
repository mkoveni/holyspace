package org.onechurch.identityaccess.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.ACCOUNT_SESSIONS;

import org.jooq.DSLContext;
import org.onechurch.identityaccess.application.contract.DeviceSessionReader;
import org.onechurch.identityaccess.application.contract.SessionReader;
import org.onechurch.identityaccess.application.contract.SessionWriter;
import org.springframework.stereotype.Repository;

import java.time.Instant;
import java.time.OffsetDateTime;
import java.time.ZoneOffset;
import java.util.List;
import java.util.Optional;
import java.util.UUID;

@Repository
public class JooqSessionStore implements SessionReader, SessionWriter, DeviceSessionReader {

    private final DSLContext db;

    public JooqSessionStore(DSLContext db) {
        this.db = db;
    }

    @Override
    public Optional<Session> find(String tokenHash) {
        return db.select(ACCOUNT_SESSIONS.ID, ACCOUNT_SESSIONS.ACCOUNT_ID, ACCOUNT_SESSIONS.EXPIRES_AT)
            .from(ACCOUNT_SESSIONS)
            .where(ACCOUNT_SESSIONS.TOKEN_HASH.eq(tokenHash))
            .fetchOptional(r -> new Session(r.value1(), r.value2(), r.value3().toInstant()));
    }

    @Override
    public Optional<Session> findById(UUID sessionId) {
        return db.select(ACCOUNT_SESSIONS.ID, ACCOUNT_SESSIONS.ACCOUNT_ID, ACCOUNT_SESSIONS.EXPIRES_AT)
            .from(ACCOUNT_SESSIONS)
            .where(ACCOUNT_SESSIONS.ID.eq(sessionId))
            .fetchOptional(r -> new Session(r.value1(), r.value2(), r.value3().toInstant()));
    }

    @Override
    public void create(UUID id, UUID accountId, String tokenHash, Instant expiresAt, Instant now) {
        create(id, accountId, tokenHash, expiresAt, now, "Web browser");
    }
    @Override
    public void create(UUID id, UUID accountId, String tokenHash, Instant expiresAt, Instant now, String deviceName) {
        db.insertInto(ACCOUNT_SESSIONS)
            .set(ACCOUNT_SESSIONS.DEVICE_NAME, deviceName)
            .set(ACCOUNT_SESSIONS.ID, id)
            .set(ACCOUNT_SESSIONS.ACCOUNT_ID, accountId)
            .set(ACCOUNT_SESSIONS.TOKEN_HASH, tokenHash)
            .set(ACCOUNT_SESSIONS.EXPIRES_AT, time(expiresAt))
            .set(ACCOUNT_SESSIONS.CREATED_AT, time(now))
            .execute();
    }

    @Override
    public void revoke(UUID sessionId, UUID accountId) {
        db.deleteFrom(ACCOUNT_SESSIONS)
            .where(ACCOUNT_SESSIONS.ID.eq(sessionId).and(ACCOUNT_SESSIONS.ACCOUNT_ID.eq(accountId)))
            .execute();
    }

    @Override
    public void revokeAll(UUID accountId) {
        db.deleteFrom(ACCOUNT_SESSIONS).where(ACCOUNT_SESSIONS.ACCOUNT_ID.eq(accountId)).execute();
    }

    @Override
    public List<Device> devices(UUID account, Instant now, int limit, int offset) {
        return db
            .select(ACCOUNT_SESSIONS.ID, ACCOUNT_SESSIONS.DEVICE_NAME, ACCOUNT_SESSIONS.CREATED_AT,
                ACCOUNT_SESSIONS.EXPIRES_AT)
            .from(ACCOUNT_SESSIONS)
            .where(ACCOUNT_SESSIONS.ACCOUNT_ID.eq(account).and(ACCOUNT_SESSIONS.EXPIRES_AT.gt(time(now))))
            .orderBy(ACCOUNT_SESSIONS.CREATED_AT.desc(), ACCOUNT_SESSIONS.ID)
            .limit(limit)
            .offset(offset)
            .fetch(r -> new Device(r.value1(), r.value2(), r.value3().toInstant(), r.value4().toInstant()));
    }
    private static OffsetDateTime time(Instant instant) {
        return instant.atOffset(ZoneOffset.UTC);
    }
}
