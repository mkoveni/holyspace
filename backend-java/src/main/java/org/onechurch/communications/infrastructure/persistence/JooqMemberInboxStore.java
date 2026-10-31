package org.onechurch.communications.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNICATION_MEMBER_NOTIFICATIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNICATION_MEMBER_PREFERENCES;

import org.jooq.Condition;
import org.jooq.DSLContext;
import org.jooq.impl.DSL;
import org.onechurch.communications.application.contract.InboxReader;
import org.onechurch.communications.application.contract.InboxWriter;
import org.onechurch.communications.application.contract.MemberNotifications;
import org.onechurch.communications.application.contract.PushWriter;
import org.onechurch.communications.domain.CommunicationException;
import org.onechurch.communications.domain.MemberNotice;
import org.onechurch.shared.infrastructure.jooq.tables.records.CommunicationMemberNotificationsRecord;
import org.springframework.stereotype.Repository;

import java.time.Clock;
import java.time.Instant;
import java.time.OffsetDateTime;
import java.time.ZoneOffset;
import java.util.List;
import java.util.UUID;
@Repository
public class JooqMemberInboxStore implements InboxReader, InboxWriter, MemberNotifications {
    private final DSLContext db;
    private final Clock clock;
    private final PushWriter push;
    public JooqMemberInboxStore(DSLContext db, Clock clock, PushWriter push) {
        this.db = db;
        this.clock = clock;
        this.push = push;
    }
    public void publish(UUID accountId, Kind kind, String sourceKey) {
        if (accountId == null) {
            return;
        }
        if (kind == null || sourceKey == null || sourceKey.isBlank() || sourceKey.length() > 200) {
            throw CommunicationException.invalid("A stable notification source is required.");
        }
        MemberNotice notice = MemberNotice.valueOf(kind.name());
        boolean enabled = db.select(COMMUNICATION_MEMBER_PREFERENCES.ENABLED)
            .from(COMMUNICATION_MEMBER_PREFERENCES)
            .where(COMMUNICATION_MEMBER_PREFERENCES.ACCOUNT_ID.eq(accountId))
            .and(COMMUNICATION_MEMBER_PREFERENCES.TOPIC.eq(notice.topic()))
            .fetchOptional(COMMUNICATION_MEMBER_PREFERENCES.ENABLED)
            .orElse(true);
        db.insertInto(COMMUNICATION_MEMBER_NOTIFICATIONS)
            .set(COMMUNICATION_MEMBER_NOTIFICATIONS.ID, UUID.randomUUID())
            .set(COMMUNICATION_MEMBER_NOTIFICATIONS.ACCOUNT_ID, accountId)
            .set(COMMUNICATION_MEMBER_NOTIFICATIONS.KIND, kind.name())
            .set(COMMUNICATION_MEMBER_NOTIFICATIONS.SOURCE_KEY, sourceKey)
            .set(COMMUNICATION_MEMBER_NOTIFICATIONS.VISIBLE, enabled)
            .set(COMMUNICATION_MEMBER_NOTIFICATIONS.CREATED_AT, time(clock.instant()))
            .onConflict(COMMUNICATION_MEMBER_NOTIFICATIONS.ACCOUNT_ID, COMMUNICATION_MEMBER_NOTIFICATIONS.KIND,
                COMMUNICATION_MEMBER_NOTIFICATIONS.SOURCE_KEY)
            .doNothing()
            .returning(COMMUNICATION_MEMBER_NOTIFICATIONS.ID)
            .fetchOptional(COMMUNICATION_MEMBER_NOTIFICATIONS.ID)
            .ifPresent(id -> push.enqueue(id, accountId, notice.topic()));
    }
    private MemberNotification view(CommunicationMemberNotificationsRecord row) {
        MemberNotice notice = MemberNotice.valueOf(row.getKind());
        return new MemberNotification(row.getId(), notice.topic(), notice.title(), notice.message(),
            notice.destination(), row.getCreatedAt().toInstant(),
            row.getReadAt() == null ? null : row.getReadAt().toInstant());
    }
    public MemberInbox list(UUID account, boolean unreadOnly, int limit, int offset) {
        Condition scope = COMMUNICATION_MEMBER_NOTIFICATIONS.ACCOUNT_ID.eq(account)
            .and(COMMUNICATION_MEMBER_NOTIFICATIONS.VISIBLE.isTrue());
        Condition filter = unreadOnly ? scope.and(COMMUNICATION_MEMBER_NOTIFICATIONS.READ_AT.isNull()) : scope;
        List<MemberNotification> rows = db.selectFrom(COMMUNICATION_MEMBER_NOTIFICATIONS)
            .where(filter)
            .orderBy(COMMUNICATION_MEMBER_NOTIFICATIONS.CREATED_AT.desc(), COMMUNICATION_MEMBER_NOTIFICATIONS.ID.desc())
            .limit(limit + 1)
            .offset(offset)
            .fetch(this::view);
        long unread = db.fetchCount(COMMUNICATION_MEMBER_NOTIFICATIONS,
            scope.and(COMMUNICATION_MEMBER_NOTIFICATIONS.READ_AT.isNull()));
        return new MemberInbox(rows.stream().limit(limit).toList(), unread, rows.size() > limit);
    }
    public List<MemberNotificationPreference> preferences(UUID account) {
        return List.of("VOLUNTEERING", "EVENTS")
            .stream()
            .map(topic -> db.selectFrom(COMMUNICATION_MEMBER_PREFERENCES)
                .where(COMMUNICATION_MEMBER_PREFERENCES.ACCOUNT_ID.eq(account))
                .and(COMMUNICATION_MEMBER_PREFERENCES.TOPIC.eq(topic))
                .fetchOptional(row -> new MemberNotificationPreference(topic, row.getEnabled(), row.getVersion()))
                .orElseGet(() -> new MemberNotificationPreference(topic, true, 0)))
            .toList();
    }
    public void read(UUID account, UUID id, boolean read, Instant at) {
        int changed = db.update(COMMUNICATION_MEMBER_NOTIFICATIONS)
            .set(COMMUNICATION_MEMBER_NOTIFICATIONS.READ_AT,
                read ? DSL.coalesce(COMMUNICATION_MEMBER_NOTIFICATIONS.READ_AT, time(at))
                    : DSL.val((OffsetDateTime) null))
            .where(COMMUNICATION_MEMBER_NOTIFICATIONS.ID.eq(id))
            .and(COMMUNICATION_MEMBER_NOTIFICATIONS.ACCOUNT_ID.eq(account))
            .and(COMMUNICATION_MEMBER_NOTIFICATIONS.VISIBLE.isTrue())
            .execute();
        if (changed == 0) {
            throw CommunicationException.missing("Notification");
        }
    }
    public void preference(UUID account, String topic, boolean enabled, long expectedVersion) {
        db.insertInto(COMMUNICATION_MEMBER_PREFERENCES)
            .set(COMMUNICATION_MEMBER_PREFERENCES.ACCOUNT_ID, account)
            .set(COMMUNICATION_MEMBER_PREFERENCES.TOPIC, topic)
            .onConflictDoNothing()
            .execute();
        long version = db.selectFrom(COMMUNICATION_MEMBER_PREFERENCES)
            .where(COMMUNICATION_MEMBER_PREFERENCES.ACCOUNT_ID.eq(account))
            .and(COMMUNICATION_MEMBER_PREFERENCES.TOPIC.eq(topic))
            .forUpdate()
            .fetchSingle()
            .getVersion();
        if (version != expectedVersion) {
            throw CommunicationException.conflict("Notification preferences changed. Refresh and try again.");
        }
        db.update(COMMUNICATION_MEMBER_PREFERENCES)
            .set(COMMUNICATION_MEMBER_PREFERENCES.ENABLED, enabled)
            .set(COMMUNICATION_MEMBER_PREFERENCES.VERSION, version + 1)
            .where(COMMUNICATION_MEMBER_PREFERENCES.ACCOUNT_ID.eq(account))
            .and(COMMUNICATION_MEMBER_PREFERENCES.TOPIC.eq(topic))
            .execute();
    }
    private static OffsetDateTime time(Instant value) {
        return value.atOffset(ZoneOffset.UTC);
    }
}
