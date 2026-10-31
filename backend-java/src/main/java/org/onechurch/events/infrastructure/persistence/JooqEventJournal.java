package org.onechurch.events.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_AUDIT;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_OCCURRENCE_HISTORY;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_REGISTRATION_HISTORY;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_REQUESTS;

import org.jooq.DSLContext;
import org.onechurch.events.application.contract.EventJournal;
import org.onechurch.events.application.contract.EventJournalReader;
import org.onechurch.events.domain.EventOccurrence;
import org.springframework.stereotype.Repository;

import java.time.Instant;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
@Repository
public class JooqEventJournal implements EventJournal, EventJournalReader {
    private final DSLContext db;
    public JooqEventJournal(DSLContext db) {
        this.db = db;
    }
    public Optional<Request> request(UUID id) {
        return db.selectFrom(EVENT_REQUESTS)
            .where(EVENT_REQUESTS.ID.eq(id))
            .fetchOptional(r -> new Request(r.getId(), r.getKind(), r.getRequestHash(), r.getTargetId(), r.getActorId(),
                r.getCreatedAt().toInstant()));
    }
    public void request(Request r) {
        db.insertInto(EVENT_REQUESTS)
            .set(EVENT_REQUESTS.ID, r.id())
            .set(EVENT_REQUESTS.KIND, r.kind())
            .set(EVENT_REQUESTS.REQUEST_HASH, r.hash())
            .set(EVENT_REQUESTS.TARGET_ID, r.targetId())
            .set(EVENT_REQUESTS.ACTOR_ID, r.actorId())
            .set(EVENT_REQUESTS.CREATED_AT, EventRecords.time(r.createdAt()))
            .execute();
    }
    public void audit(UUID actor, String action, UUID target, String reason, Instant at) {
        db.insertInto(EVENT_AUDIT)
            .set(EVENT_AUDIT.ID, UUID.randomUUID())
            .set(EVENT_AUDIT.ACTOR_ID, actor)
            .set(EVENT_AUDIT.ACTION, action)
            .set(EVENT_AUDIT.TARGET_ID, target)
            .set(EVENT_AUDIT.REASON, reason)
            .set(EVENT_AUDIT.OCCURRED_AT, EventRecords.time(at))
            .execute();
    }
    public void occurrence(UUID actor, String action, EventOccurrence o, String reason, Instant at) {
        db.insertInto(EVENT_OCCURRENCE_HISTORY)
            .set(EVENT_OCCURRENCE_HISTORY.ID, UUID.randomUUID())
            .set(EVENT_OCCURRENCE_HISTORY.OCCURRENCE_ID, o.id())
            .set(EVENT_OCCURRENCE_HISTORY.ACTOR_ID, actor)
            .set(EVENT_OCCURRENCE_HISTORY.ACTION, action)
            .set(EVENT_OCCURRENCE_HISTORY.REASON, reason)
            .set(EVENT_OCCURRENCE_HISTORY.STARTS_AT, EventRecords.time(o.startsAt()))
            .set(EVENT_OCCURRENCE_HISTORY.ENDS_AT, EventRecords.time(o.endsAt()))
            .set(EVENT_OCCURRENCE_HISTORY.VENUE_ID, o.venueId())
            .set(EVENT_OCCURRENCE_HISTORY.VENUE_NAME, o.venueName())
            .set(EVENT_OCCURRENCE_HISTORY.VENUE_ADDRESS, o.venueAddress())
            .set(EVENT_OCCURRENCE_HISTORY.STATUS, o.status())
            .set(EVENT_OCCURRENCE_HISTORY.VERSION, o.version())
            .set(EVENT_OCCURRENCE_HISTORY.SCHEDULE_REVISION, o.scheduleRevision())
            .set(EVENT_OCCURRENCE_HISTORY.OCCURRED_AT, EventRecords.time(at))
            .execute();
    }
    public void registration(UUID actor, String action, UUID target, String reason, Instant at) {
        db.insertInto(EVENT_REGISTRATION_HISTORY)
            .set(EVENT_REGISTRATION_HISTORY.ID, UUID.randomUUID())
            .set(EVENT_REGISTRATION_HISTORY.REGISTRATION_ID, target)
            .set(EVENT_REGISTRATION_HISTORY.ACTOR_ID, actor)
            .set(EVENT_REGISTRATION_HISTORY.ACTION, action)
            .set(EVENT_REGISTRATION_HISTORY.REASON, reason)
            .set(EVENT_REGISTRATION_HISTORY.OCCURRED_AT, EventRecords.time(at))
            .execute();
    }
    public List<RegistrationEntry> registrationHistory(UUID id, int limit, int offset) {
        return db.selectFrom(EVENT_REGISTRATION_HISTORY)
            .where(EVENT_REGISTRATION_HISTORY.REGISTRATION_ID.eq(id))
            .orderBy(EVENT_REGISTRATION_HISTORY.OCCURRED_AT.desc(), EVENT_REGISTRATION_HISTORY.ID)
            .limit(limit)
            .offset(offset)
            .fetch(r -> new RegistrationEntry(r.getId(), r.getAction(), r.getReason(), r.getOccurredAt().toInstant()));
    }
    public List<OccurrenceEntry> occurrenceHistory(UUID id, int limit, int offset) {
        return db.selectFrom(EVENT_OCCURRENCE_HISTORY)
            .where(EVENT_OCCURRENCE_HISTORY.OCCURRENCE_ID.eq(id))
            .orderBy(EVENT_OCCURRENCE_HISTORY.VERSION.desc(), EVENT_OCCURRENCE_HISTORY.ID)
            .limit(limit)
            .offset(offset)
            .fetch(r -> new OccurrenceEntry(r.getId(), r.getAction(), r.getReason(), r.getStartsAt().toInstant(),
                r.getEndsAt().toInstant(), r.getVenueId(), r.getVenueName(), r.getVenueAddress(), r.getStatus(),
                r.getVersion(), r.getScheduleRevision(), r.getOccurredAt().toInstant()));
    }
}
