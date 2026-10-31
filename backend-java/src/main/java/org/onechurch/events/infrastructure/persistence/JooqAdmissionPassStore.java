package org.onechurch.events.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_ADMISSION_PASSES;

import org.jooq.DSLContext;
import org.onechurch.events.domain.AdmissionPass;
import org.onechurch.events.domain.repository.AdmissionPassRepository;
import org.onechurch.shared.infrastructure.jooq.tables.records.EventAdmissionPassesRecord;
import org.springframework.stereotype.Repository;

import java.time.Instant;
import java.util.Optional;
@Repository
public class JooqAdmissionPassStore implements AdmissionPassRepository {
    private final DSLContext db;
    public JooqAdmissionPassStore(DSLContext db) {
        this.db = db;
    }
    private AdmissionPass pass(EventAdmissionPassesRecord r) {
        return new AdmissionPass(r.getTokenHash(), r.getRegistrationId(), r.getAccountId(), r.getSessionId(),
            r.getMemberVersion(), r.getRegistrationVersion(), r.getScheduleRevision(), r.getExpiresAt().toInstant(),
            r.getUsedAt() == null ? null : r.getUsedAt().toInstant());
    }
    public void insert(AdmissionPass p) {
        db.insertInto(EVENT_ADMISSION_PASSES)
            .set(EVENT_ADMISSION_PASSES.TOKEN_HASH, p.tokenHash())
            .set(EVENT_ADMISSION_PASSES.REGISTRATION_ID, p.registrationId())
            .set(EVENT_ADMISSION_PASSES.ACCOUNT_ID, p.accountId())
            .set(EVENT_ADMISSION_PASSES.SESSION_ID, p.sessionId())
            .set(EVENT_ADMISSION_PASSES.MEMBER_VERSION, p.memberVersion())
            .set(EVENT_ADMISSION_PASSES.REGISTRATION_VERSION, p.registrationVersion())
            .set(EVENT_ADMISSION_PASSES.SCHEDULE_REVISION, p.scheduleRevision())
            .set(EVENT_ADMISSION_PASSES.EXPIRES_AT, EventRecords.time(p.expiresAt()))
            .execute();
    }
    public Optional<AdmissionPass> find(String hash) {
        return db.selectFrom(EVENT_ADMISSION_PASSES)
            .where(EVENT_ADMISSION_PASSES.TOKEN_HASH.eq(hash))
            .fetchOptional(this::pass);
    }
    public Optional<AdmissionPass> lock(String hash) {
        return db.selectFrom(EVENT_ADMISSION_PASSES)
            .where(EVENT_ADMISSION_PASSES.TOKEN_HASH.eq(hash))
            .forUpdate()
            .fetchOptional(this::pass);
    }
    public void used(String hash, Instant now) {
        db.update(EVENT_ADMISSION_PASSES)
            .set(EVENT_ADMISSION_PASSES.USED_AT, EventRecords.time(now))
            .where(EVENT_ADMISSION_PASSES.TOKEN_HASH.eq(hash))
            .execute();
    }
}
