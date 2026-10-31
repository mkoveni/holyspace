package org.onechurch.communications.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNICATION_MEMBER_NOTIFICATIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNICATION_PUSH_DELIVERIES;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNICATION_PUSH_DEVICES;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNICATION_PUSH_PREFERENCES;

import org.jooq.DSLContext;
import org.onechurch.communications.application.PushQueue;
import org.onechurch.communications.application.contract.PushWriter;
import org.onechurch.communications.domain.CommunicationException;
import org.onechurch.communications.domain.PushDevice;
import org.onechurch.shared.infrastructure.jooq.tables.records.CommunicationPushDeliveriesRecord;
import org.springframework.stereotype.Repository;

import java.time.Clock;
import java.time.Instant;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
@Repository
public class JooqPushQueueStore implements PushQueue, PushWriter {
    private final DSLContext db;
    private final Clock clock;
    public JooqPushQueueStore(DSLContext db, Clock clock) {
        this.db = db;
        this.clock = clock;
    }
    public void preference(UUID account, String topic, boolean enabled, long expected) {
        db.insertInto(COMMUNICATION_PUSH_PREFERENCES)
            .set(COMMUNICATION_PUSH_PREFERENCES.ACCOUNT_ID, account)
            .set(COMMUNICATION_PUSH_PREFERENCES.TOPIC, topic)
            .onConflictDoNothing()
            .execute();
        long version = db.selectFrom(COMMUNICATION_PUSH_PREFERENCES)
            .where(COMMUNICATION_PUSH_PREFERENCES.ACCOUNT_ID.eq(account))
            .and(COMMUNICATION_PUSH_PREFERENCES.TOPIC.eq(topic))
            .forUpdate()
            .fetchSingle()
            .getVersion();
        if (version != expected) {
            throw CommunicationException.conflict("Push preferences changed. Refresh and try again.");
        }
        db.update(COMMUNICATION_PUSH_PREFERENCES)
            .set(COMMUNICATION_PUSH_PREFERENCES.ENABLED, enabled)
            .set(COMMUNICATION_PUSH_PREFERENCES.VERSION, version + 1)
            .where(COMMUNICATION_PUSH_PREFERENCES.ACCOUNT_ID.eq(account))
            .and(COMMUNICATION_PUSH_PREFERENCES.TOPIC.eq(topic))
            .execute();
    }
    public void enqueue(UUID notification, UUID account, String topic) {
        Instant at = clock.instant();
        db.select(COMMUNICATION_PUSH_DEVICES.ID, COMMUNICATION_PUSH_DEVICES.REVISION,
            COMMUNICATION_PUSH_PREFERENCES.VERSION)
            .from(COMMUNICATION_PUSH_DEVICES)
            .join(COMMUNICATION_PUSH_PREFERENCES)
            .on(COMMUNICATION_PUSH_PREFERENCES.ACCOUNT_ID.eq(COMMUNICATION_PUSH_DEVICES.ACCOUNT_ID))
            .where(COMMUNICATION_PUSH_DEVICES.ACCOUNT_ID.eq(account))
            .and(COMMUNICATION_PUSH_DEVICES.STATUS.eq("ACTIVE"))
            .and(COMMUNICATION_PUSH_DEVICES.EXPIRES_AT.gt(JooqPushDeviceStore.time(at)))
            .and(COMMUNICATION_PUSH_PREFERENCES.TOPIC.eq(topic))
            .and(COMMUNICATION_PUSH_PREFERENCES.ENABLED.isTrue())
            .limit(100)
            .fetch()
            .forEach(row -> db.insertInto(COMMUNICATION_PUSH_DELIVERIES)
                .set(COMMUNICATION_PUSH_DELIVERIES.ID, UUID.randomUUID())
                .set(COMMUNICATION_PUSH_DELIVERIES.NOTIFICATION_ID, notification)
                .set(COMMUNICATION_PUSH_DELIVERIES.DEVICE_ID, row.value1())
                .set(COMMUNICATION_PUSH_DELIVERIES.DEVICE_REVISION, row.value2())
                .set(COMMUNICATION_PUSH_DELIVERIES.TOPIC, topic)
                .set(COMMUNICATION_PUSH_DELIVERIES.PREFERENCE_VERSION, row.value3())
                .set(COMMUNICATION_PUSH_DELIVERIES.STATUS, "QUEUED")
                .set(COMMUNICATION_PUSH_DELIVERIES.DUE_AT, JooqPushDeviceStore.time(at))
                .set(COMMUNICATION_PUSH_DELIVERIES.CREATED_AT, JooqPushDeviceStore.time(at))
                .onConflict(COMMUNICATION_PUSH_DELIVERIES.NOTIFICATION_ID, COMMUNICATION_PUSH_DELIVERIES.DEVICE_ID)
                .doNothing()
                .execute());
    }
    public List<UUID> due(Instant at) {
        return db.select(COMMUNICATION_PUSH_DELIVERIES.ID)
            .from(COMMUNICATION_PUSH_DELIVERIES)
            .where(COMMUNICATION_PUSH_DELIVERIES.STATUS.in("QUEUED", "RETRY", "TICKET"))
            .and(COMMUNICATION_PUSH_DELIVERIES.DUE_AT.le(JooqPushDeviceStore.time(at)))
            .orderBy(COMMUNICATION_PUSH_DELIVERIES.DUE_AT, COMMUNICATION_PUSH_DELIVERIES.ID)
            .limit(25)
            .fetch(COMMUNICATION_PUSH_DELIVERIES.ID);
    }
    private Job job(CommunicationPushDeliveriesRecord r) {
        return new Job(r.getId(), r.getNotificationId(), r.getDeviceId(), r.getDeviceRevision(), r.getTopic(),
            r.getPreferenceVersion(), r.getStatus(), r.getAttempts(), r.getCreatedAt().toInstant(), r.getClaimId(),
            r.getTicketId());
    }
    public Optional<Job> claim(UUID id, Instant at) {
        return db.selectFrom(COMMUNICATION_PUSH_DELIVERIES)
            .where(COMMUNICATION_PUSH_DELIVERIES.ID.eq(id))
            .and(COMMUNICATION_PUSH_DELIVERIES.STATUS.in("QUEUED", "RETRY", "TICKET"))
            .and(COMMUNICATION_PUSH_DELIVERIES.DUE_AT.le(JooqPushDeviceStore.time(at)))
            .forUpdate()
            .skipLocked()
            .fetchOptional()
            .map(row -> {
                boolean receipt = row.getStatus().equals("TICKET");
                row.setStatus(receipt ? "CHECKING" : "SENDING");
                row.setClaimId(UUID.randomUUID());
                row.setLeaseUntil(JooqPushDeviceStore.time(at.plusSeconds(120)));
                if (!receipt) {
                    row.setAttempts(row.getAttempts() + 1);
                }
                row.update();
                return job(row);
            });
    }
    public Optional<PushDevice> eligible(Job j, Instant at) {
        return db.select(COMMUNICATION_PUSH_DEVICES.fields())
            .from(COMMUNICATION_PUSH_DEVICES)
            .join(COMMUNICATION_PUSH_PREFERENCES)
            .on(COMMUNICATION_PUSH_PREFERENCES.ACCOUNT_ID.eq(COMMUNICATION_PUSH_DEVICES.ACCOUNT_ID))
            .join(COMMUNICATION_MEMBER_NOTIFICATIONS)
            .on(COMMUNICATION_MEMBER_NOTIFICATIONS.ACCOUNT_ID.eq(COMMUNICATION_PUSH_DEVICES.ACCOUNT_ID))
            .where(COMMUNICATION_PUSH_DEVICES.ID.eq(j.deviceId()))
            .and(COMMUNICATION_PUSH_DEVICES.REVISION.eq(j.deviceRevision()))
            .and(COMMUNICATION_PUSH_DEVICES.STATUS.eq("ACTIVE"))
            .and(COMMUNICATION_PUSH_DEVICES.EXPIRES_AT.gt(JooqPushDeviceStore.time(at)))
            .and(COMMUNICATION_MEMBER_NOTIFICATIONS.ID.eq(j.notificationId()))
            .and(COMMUNICATION_PUSH_PREFERENCES.TOPIC.eq(j.topic()))
            .and(COMMUNICATION_PUSH_PREFERENCES.ENABLED.isTrue())
            .and(COMMUNICATION_PUSH_PREFERENCES.VERSION.eq(j.preferenceVersion()))
            .fetchOptional(row -> JooqPushDeviceStore.device(row.into(COMMUNICATION_PUSH_DEVICES)));
    }
    public void finish(Job j, String state, Instant due, String ticket, String error, Instant at) {
        int changed = db.update(COMMUNICATION_PUSH_DELIVERIES)
            .set(COMMUNICATION_PUSH_DELIVERIES.STATUS, state)
            .set(COMMUNICATION_PUSH_DELIVERIES.DUE_AT, JooqPushDeviceStore.time(due))
            .set(COMMUNICATION_PUSH_DELIVERIES.TICKET_ID, ticket)
            .set(COMMUNICATION_PUSH_DELIVERIES.ERROR_CODE, error)
            .set(COMMUNICATION_PUSH_DELIVERIES.LEASE_UNTIL, (java.time.OffsetDateTime) null)
            .where(COMMUNICATION_PUSH_DELIVERIES.ID.eq(j.id()))
            .and(COMMUNICATION_PUSH_DELIVERIES.CLAIM_ID.eq(j.claimId()))
            .and(COMMUNICATION_PUSH_DELIVERIES.STATUS.in("SENDING", "CHECKING"))
            .execute();
        if (changed > 0 && error.equals("DeviceNotRegistered")) {
            db.update(COMMUNICATION_PUSH_DEVICES)
                .set(COMMUNICATION_PUSH_DEVICES.STATUS, "INVALID")
                .set(COMMUNICATION_PUSH_DEVICES.REVISION, COMMUNICATION_PUSH_DEVICES.REVISION.plus(1))
                .where(COMMUNICATION_PUSH_DEVICES.ID.eq(j.deviceId()))
                .and(COMMUNICATION_PUSH_DEVICES.REVISION.eq(j.deviceRevision()))
                .execute();
        }
    }
    public void recover(Instant at) {
        db.update(COMMUNICATION_PUSH_DELIVERIES)
            .set(COMMUNICATION_PUSH_DELIVERIES.STATUS, "UNKNOWN")
            .set(COMMUNICATION_PUSH_DELIVERIES.ERROR_CODE, "LEASE_EXPIRED")
            .where(COMMUNICATION_PUSH_DELIVERIES.ID.in(db.select(COMMUNICATION_PUSH_DELIVERIES.ID)
                .from(COMMUNICATION_PUSH_DELIVERIES)
                .where(COMMUNICATION_PUSH_DELIVERIES.STATUS.eq("SENDING"))
                .and(COMMUNICATION_PUSH_DELIVERIES.LEASE_UNTIL.lt(JooqPushDeviceStore.time(at)))
                .limit(1000)))
            .execute();
        db.update(COMMUNICATION_PUSH_DELIVERIES)
            .set(COMMUNICATION_PUSH_DELIVERIES.STATUS, "TICKET")
            .set(COMMUNICATION_PUSH_DELIVERIES.DUE_AT, JooqPushDeviceStore.time(at))
            .where(COMMUNICATION_PUSH_DELIVERIES.ID.in(db.select(COMMUNICATION_PUSH_DELIVERIES.ID)
                .from(COMMUNICATION_PUSH_DELIVERIES)
                .where(COMMUNICATION_PUSH_DELIVERIES.STATUS.eq("CHECKING"))
                .and(COMMUNICATION_PUSH_DELIVERIES.LEASE_UNTIL.lt(JooqPushDeviceStore.time(at)))
                .limit(1000)))
            .execute();
        db.update(COMMUNICATION_PUSH_DEVICES)
            .set(COMMUNICATION_PUSH_DEVICES.STATUS, "REVOKED")
            .set(COMMUNICATION_PUSH_DEVICES.REVISION, COMMUNICATION_PUSH_DEVICES.REVISION.plus(1))
            .where(COMMUNICATION_PUSH_DEVICES.ID.in(db.select(COMMUNICATION_PUSH_DEVICES.ID)
                .from(COMMUNICATION_PUSH_DEVICES)
                .where(COMMUNICATION_PUSH_DEVICES.STATUS.eq("ACTIVE"))
                .and(COMMUNICATION_PUSH_DEVICES.EXPIRES_AT.le(JooqPushDeviceStore.time(at)))
                .limit(1000)))
            .execute();
    }
}
