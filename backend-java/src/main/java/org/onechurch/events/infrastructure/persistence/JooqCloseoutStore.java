package org.onechurch.events.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_ATTENDANCE;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_CLOSEOUTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_CLOSEOUT_HISTORY;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_DEFINITIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_NOTIFICATIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_OCCURRENCES;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_REGISTRATIONS;

import org.jooq.DSLContext;
import org.jooq.impl.DSL;
import org.onechurch.events.application.contract.CloseoutReader;
import org.onechurch.events.domain.OccurrenceCloseout;
import org.onechurch.events.domain.repository.CloseoutRepository;
import org.springframework.stereotype.Repository;

import java.util.List;
import java.util.Optional;
import java.util.UUID;
@Repository
public class JooqCloseoutStore implements CloseoutReader, CloseoutRepository {
    private final DSLContext db;
    public JooqCloseoutStore(DSLContext db) {
        this.db = db;
    }
    public Optional<OccurrenceCloseout> closeout(UUID id) {
        return db.selectFrom(EVENT_CLOSEOUTS)
            .where(EVENT_CLOSEOUTS.OCCURRENCE_ID.eq(id))
            .fetchOptional(r -> new OccurrenceCloseout(r.getOccurrenceId(), r.getStatus(), r.getVersion(), r.getNote(),
                r.getFollowUp(), r.getReviewHash().trim(), r.getUpdatedBy(), r.getUpdatedAt().toInstant()));
    }
    public List<History> history(UUID id, int limit, int offset) {
        return db.selectFrom(EVENT_CLOSEOUT_HISTORY)
            .where(EVENT_CLOSEOUT_HISTORY.OCCURRENCE_ID.eq(id))
            .orderBy(EVENT_CLOSEOUT_HISTORY.VERSION.desc())
            .limit(limit)
            .offset(offset)
            .fetch(r -> new History(r.getId(), r.getVersion(), r.getAction(), r.getNote(), r.getFollowUp(),
                r.getAttended(), r.getAbsent(), r.getWaitlisted(), r.getUnresolvedNotices(), r.getActorId(), "",
                r.getOccurredAt().toInstant()));
    }
    public List<Notice> notices(UUID id, int limit) {
        return db
            .select(EVENT_NOTIFICATIONS.ID, EVENT_NOTIFICATIONS.REGISTRATION_ID, EVENT_NOTIFICATIONS.PURPOSE,
                EVENT_NOTIFICATIONS.REGISTRATION_VERSION, EVENT_NOTIFICATIONS.SCHEDULE_REVISION,
                EVENT_NOTIFICATIONS.EVENT_VERSION)
            .from(EVENT_NOTIFICATIONS)
            .join(EVENT_REGISTRATIONS)
            .on(EVENT_NOTIFICATIONS.REGISTRATION_ID.eq(EVENT_REGISTRATIONS.ID))
            .where(EVENT_REGISTRATIONS.OCCURRENCE_ID.eq(id))
            .orderBy(EVENT_NOTIFICATIONS.ID)
            .limit(limit)
            .fetch(r -> new Notice(r.value1(), r.value2(), r.value3(), r.value4(), r.value5(), r.value6()));
    }
    public long currentReminderBookings(UUID id) {
        return db.selectCount()
            .from(EVENT_REGISTRATIONS)
            .join(EVENT_OCCURRENCES)
            .on(EVENT_REGISTRATIONS.OCCURRENCE_ID.eq(EVENT_OCCURRENCES.ID))
            .join(EVENT_DEFINITIONS)
            .on(EVENT_OCCURRENCES.EVENT_ID.eq(EVENT_DEFINITIONS.ID))
            .where(EVENT_REGISTRATIONS.OCCURRENCE_ID.eq(id).and(EVENT_REGISTRATIONS.STATUS.eq("CONFIRMED")))
            .andExists(db.selectOne()
                .from(EVENT_NOTIFICATIONS)
                .where(EVENT_NOTIFICATIONS.REGISTRATION_ID.eq(EVENT_REGISTRATIONS.ID))
                .and(EVENT_NOTIFICATIONS.PURPOSE.eq("REMINDER"))
                .and(EVENT_NOTIFICATIONS.REGISTRATION_VERSION.eq(EVENT_REGISTRATIONS.VERSION))
                .and(EVENT_NOTIFICATIONS.SCHEDULE_REVISION.eq(EVENT_OCCURRENCES.SCHEDULE_REVISION))
                .and(EVENT_NOTIFICATIONS.EVENT_VERSION.eq(EVENT_DEFINITIONS.VERSION)))
            .fetchOne(0, long.class);
    }
    public long attendanceRevision(UUID id) {
        return db.select(DSL.coalesce(DSL.sum(EVENT_ATTENDANCE.VERSION.plus(1)), java.math.BigDecimal.ZERO))
            .from(EVENT_ATTENDANCE)
            .join(EVENT_REGISTRATIONS)
            .on(EVENT_ATTENDANCE.REGISTRATION_ID.eq(EVENT_REGISTRATIONS.ID))
            .where(EVENT_REGISTRATIONS.OCCURRENCE_ID.eq(id))
            .fetchOne(0, long.class);
    }
    public void save(OccurrenceCloseout c, long attended, long absent, long waitlisted, long unresolved) {
        db.insertInto(EVENT_CLOSEOUTS)
            .set(EVENT_CLOSEOUTS.OCCURRENCE_ID, c.occurrenceId())
            .set(EVENT_CLOSEOUTS.STATUS, c.status())
            .set(EVENT_CLOSEOUTS.VERSION, c.version())
            .set(EVENT_CLOSEOUTS.NOTE, c.note())
            .set(EVENT_CLOSEOUTS.FOLLOW_UP, c.followUp())
            .set(EVENT_CLOSEOUTS.REVIEW_HASH, c.reviewHash())
            .set(EVENT_CLOSEOUTS.UPDATED_BY, c.updatedBy())
            .set(EVENT_CLOSEOUTS.UPDATED_AT, EventRecords.time(c.updatedAt()))
            .onConflict(EVENT_CLOSEOUTS.OCCURRENCE_ID)
            .doUpdate()
            .set(EVENT_CLOSEOUTS.STATUS, c.status())
            .set(EVENT_CLOSEOUTS.VERSION, c.version())
            .set(EVENT_CLOSEOUTS.NOTE, c.note())
            .set(EVENT_CLOSEOUTS.FOLLOW_UP, c.followUp())
            .set(EVENT_CLOSEOUTS.REVIEW_HASH, c.reviewHash())
            .set(EVENT_CLOSEOUTS.UPDATED_BY, c.updatedBy())
            .set(EVENT_CLOSEOUTS.UPDATED_AT, EventRecords.time(c.updatedAt()))
            .execute();
        db.insertInto(EVENT_CLOSEOUT_HISTORY)
            .set(EVENT_CLOSEOUT_HISTORY.ID, UUID.randomUUID())
            .set(EVENT_CLOSEOUT_HISTORY.OCCURRENCE_ID, c.occurrenceId())
            .set(EVENT_CLOSEOUT_HISTORY.VERSION, c.version())
            .set(EVENT_CLOSEOUT_HISTORY.ACTION, c.status().equals("CLOSED") ? "CLOSED" : "REOPENED")
            .set(EVENT_CLOSEOUT_HISTORY.NOTE, c.note())
            .set(EVENT_CLOSEOUT_HISTORY.FOLLOW_UP, c.followUp())
            .set(EVENT_CLOSEOUT_HISTORY.REVIEW_HASH, c.reviewHash())
            .set(EVENT_CLOSEOUT_HISTORY.ATTENDED, attended)
            .set(EVENT_CLOSEOUT_HISTORY.ABSENT, absent)
            .set(EVENT_CLOSEOUT_HISTORY.WAITLISTED, waitlisted)
            .set(EVENT_CLOSEOUT_HISTORY.UNRESOLVED_NOTICES, unresolved)
            .set(EVENT_CLOSEOUT_HISTORY.ACTOR_ID, c.updatedBy())
            .set(EVENT_CLOSEOUT_HISTORY.OCCURRED_AT, EventRecords.time(c.updatedAt()))
            .execute();
    }
}
