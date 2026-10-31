package org.onechurch.communications.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNICATION_MEMBER_NOTIFICATIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNICATION_PUSH_DEVICES;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNICATION_PUSH_PREFERENCES;

import org.jooq.DSLContext;
import org.onechurch.communications.application.contract.InboxReader;
import org.onechurch.communications.application.contract.PushReader;
import org.onechurch.communications.domain.CommunicationException;
import org.onechurch.communications.domain.MemberNotice;
import org.onechurch.communications.domain.PushDevice;
import org.onechurch.communications.domain.repository.PushDeviceRepository;
import org.onechurch.shared.infrastructure.jooq.tables.records.CommunicationPushDevicesRecord;
import org.springframework.dao.DuplicateKeyException;
import org.springframework.stereotype.Repository;

import java.time.Instant;
import java.time.OffsetDateTime;
import java.time.ZoneOffset;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
@Repository
public class JooqPushDeviceStore implements PushReader, PushDeviceRepository {
    private final DSLContext db;
    public JooqPushDeviceStore(DSLContext db) {
        this.db = db;
    }
    static OffsetDateTime time(Instant at) {
        return at.atOffset(ZoneOffset.UTC);
    }
    static PushDevice device(CommunicationPushDevicesRecord r) {
        return new PushDevice(r.getId(), r.getAccountId(), r.getSessionId(), r.getSecretHash(), r.getTokenHash(),
            r.getEncryptedToken(), r.getProjectId(), r.getPlatform(), r.getStatus(), r.getRevision(),
            r.getExpiresAt().toInstant(), r.getUpdatedAt().toInstant());
    }
    private CommunicationPushDevicesRecord record(PushDevice d) {
        CommunicationPushDevicesRecord row = db.newRecord(COMMUNICATION_PUSH_DEVICES);
        row.setId(d.id());
        row.setAccountId(d.accountId());
        row.setSessionId(d.sessionId());
        row.setSecretHash(d.secretHash());
        row.setTokenHash(d.tokenHash());
        row.setEncryptedToken(d.encryptedToken());
        row.setProjectId(d.projectId());
        row.setPlatform(d.platform());
        row.setStatus(d.status());
        row.setRevision(d.revision());
        row.setExpiresAt(time(d.expiresAt()));
        row.setUpdatedAt(time(d.updatedAt()));
        return row;
    }
    public void ensure(PushDevice d) {
        // Reinstalling on the same account retires that account's old installation for this token.
        db.update(COMMUNICATION_PUSH_DEVICES)
            .set(COMMUNICATION_PUSH_DEVICES.STATUS, "REVOKED")
            .set(COMMUNICATION_PUSH_DEVICES.REVISION, COMMUNICATION_PUSH_DEVICES.REVISION.plus(1))
            .where(COMMUNICATION_PUSH_DEVICES.TOKEN_HASH.eq(d.tokenHash()))
            .and(COMMUNICATION_PUSH_DEVICES.ACCOUNT_ID.eq(d.accountId()))
            .and(COMMUNICATION_PUSH_DEVICES.ID.ne(d.id()))
            .and(COMMUNICATION_PUSH_DEVICES.STATUS.eq("ACTIVE"))
            .execute();
        try {
            db.insertInto(COMMUNICATION_PUSH_DEVICES)
                .set(record(d))
                .onConflict(COMMUNICATION_PUSH_DEVICES.ID)
                .doNothing()
                .execute();
        } catch (DuplicateKeyException e) {
            throw CommunicationException
                .conflict("This push token is already registered. Sign out of the previous device registration first.");
        }
    }
    public Optional<PushDevice> lock(UUID id) {
        return db.selectFrom(COMMUNICATION_PUSH_DEVICES)
            .where(COMMUNICATION_PUSH_DEVICES.ID.eq(id))
            .forUpdate()
            .fetchOptional(JooqPushDeviceStore::device);
    }
    public void save(PushDevice d) {
        try {
            db.update(COMMUNICATION_PUSH_DEVICES)
                .set(record(d))
                .where(COMMUNICATION_PUSH_DEVICES.ID.eq(d.id()))
                .execute();
        } catch (DuplicateKeyException e) {
            throw CommunicationException.conflict("This push token is already registered to another installation.");
        }
    }
    public List<PushDeviceView> devices(UUID account, UUID session) {
        return db.selectFrom(COMMUNICATION_PUSH_DEVICES)
            .where(COMMUNICATION_PUSH_DEVICES.ACCOUNT_ID.eq(account))
            .orderBy(COMMUNICATION_PUSH_DEVICES.UPDATED_AT.desc(), COMMUNICATION_PUSH_DEVICES.ID)
            .limit(100)
            .fetch(d -> new PushDeviceView(d.getId(), d.getPlatform(), d.getStatus(), d.getExpiresAt().toInstant(),
                d.getUpdatedAt().toInstant(), d.getSessionId().equals(session)));
    }
    public List<PushPreference> preferences(UUID account) {
        return List.of("VOLUNTEERING", "EVENTS")
            .stream()
            .map(topic -> db.selectFrom(COMMUNICATION_PUSH_PREFERENCES)
                .where(COMMUNICATION_PUSH_PREFERENCES.ACCOUNT_ID.eq(account))
                .and(COMMUNICATION_PUSH_PREFERENCES.TOPIC.eq(topic))
                .fetchOptional(p -> new PushPreference(topic, p.getEnabled(), p.getVersion()))
                .orElseGet(() -> new PushPreference(topic, false, 0)))
            .toList();
    }
    public Optional<InboxReader.MemberNotification> notification(UUID account, UUID id) {
        return db.selectFrom(COMMUNICATION_MEMBER_NOTIFICATIONS)
            .where(COMMUNICATION_MEMBER_NOTIFICATIONS.ACCOUNT_ID.eq(account))
            .and(COMMUNICATION_MEMBER_NOTIFICATIONS.ID.eq(id))
            .fetchOptional(row -> {
                MemberNotice n = MemberNotice.valueOf(row.getKind());
                return new InboxReader.MemberNotification(id, n.topic(), n.title(), n.message(), n.destination(),
                    row.getCreatedAt().toInstant(), row.getReadAt() == null ? null : row.getReadAt().toInstant());
            });
    }
}
