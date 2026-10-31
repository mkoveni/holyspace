package org.onechurch.events.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_DEFINITIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_OCCURRENCES;

import org.jooq.Condition;
import org.jooq.DSLContext;
import org.jooq.impl.DSL;
import org.onechurch.events.application.contract.OccurrenceReader;
import org.onechurch.events.domain.EventException;
import org.onechurch.events.domain.EventOccurrence;
import org.onechurch.events.domain.repository.OccurrenceRepository;
import org.springframework.stereotype.Repository;

import java.time.Instant;
import java.time.LocalDate;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
@Repository
public class JooqOccurrenceStore implements OccurrenceReader, OccurrenceRepository {
    private final DSLContext db;
    public JooqOccurrenceStore(DSLContext db) {
        this.db = db;
    }
    public List<EventOccurrence> byIds(List<UUID> ids) {
        return db.selectFrom(EVENT_OCCURRENCES).where(EVENT_OCCURRENCES.ID.in(ids)).fetch(EventRecords::occurrence);
    }
    public Optional<EventOccurrence> occurrence(UUID id) {
        return db.selectFrom(EVENT_OCCURRENCES)
            .where(EVENT_OCCURRENCES.ID.eq(id))
            .fetchOptional(EventRecords::occurrence);
    }
    public Optional<EventOccurrence> lockOccurrence(UUID id) {
        return db.selectFrom(EVENT_OCCURRENCES)
            .where(EVENT_OCCURRENCES.ID.eq(id))
            .forUpdate()
            .fetchOptional(EventRecords::occurrence);
    }
    private Condition matching(UUID event, String search, Instant from, Instant until, String status) {
        return EVENT_OCCURRENCES.STARTS_AT.lt(EventRecords.time(until))
            .and(EVENT_OCCURRENCES.ENDS_AT.gt(EventRecords.time(from)))
            .and(event == null ? DSL.noCondition() : EVENT_OCCURRENCES.EVENT_ID.eq(event))
            .and(status == null ? DSL.noCondition() : EVENT_OCCURRENCES.STATUS.eq(status))
            .and(EVENT_DEFINITIONS.TITLE.containsIgnoreCase(search));
    }
    public List<EventOccurrence> calendar(UUID event, String search, Instant from, Instant until, String status,
        int limit, int offset) {
        return db.select(EVENT_OCCURRENCES.fields())
            .from(EVENT_OCCURRENCES)
            .join(EVENT_DEFINITIONS)
            .on(EVENT_DEFINITIONS.ID.eq(EVENT_OCCURRENCES.EVENT_ID))
            .where(matching(event, search, from, until, status))
            .orderBy(EVENT_OCCURRENCES.STARTS_AT, EVENT_OCCURRENCES.ID)
            .limit(limit)
            .offset(offset)
            .fetch(r -> EventRecords.occurrence(r.into(EVENT_OCCURRENCES)));
    }
    public long calendarCount(UUID event, String search, Instant from, Instant until, String status) {
        return db.selectCount()
            .from(EVENT_OCCURRENCES)
            .join(EVENT_DEFINITIONS)
            .on(EVENT_DEFINITIONS.ID.eq(EVENT_OCCURRENCES.EVENT_ID))
            .where(matching(event, search, from, until, status))
            .fetchSingle(0, int.class);
    }
    public List<EventOccurrence> futureSeries(UUID series, LocalDate origin, Instant now, int limit) {
        return db.selectFrom(EVENT_OCCURRENCES)
            .where(EVENT_OCCURRENCES.SERIES_ID.eq(series)
                .and(EVENT_OCCURRENCES.ORIGIN_DATE.ge(origin))
                .and(EVENT_OCCURRENCES.ENDS_AT.gt(EventRecords.time(now))))
            .orderBy(EVENT_OCCURRENCES.ORIGIN_DATE, EVENT_OCCURRENCES.ID)
            .limit(limit)
            .fetch(EventRecords::occurrence);
    }
    public boolean venueClash(UUID venue, Instant from, Instant until, List<UUID> exclude) {
        return db.fetchExists(db.selectOne()
            .from(EVENT_OCCURRENCES)
            .where(EVENT_OCCURRENCES.VENUE_ID.eq(venue)
                .and(EVENT_OCCURRENCES.STATUS.eq("SCHEDULED"))
                .and(EVENT_OCCURRENCES.EXCLUSIVE.isTrue())
                .and(EVENT_OCCURRENCES.STARTS_AT.lt(EventRecords.time(until)))
                .and(EVENT_OCCURRENCES.ENDS_AT.gt(EventRecords.time(from)))
                .and(exclude.isEmpty() ? DSL.noCondition() : EVENT_OCCURRENCES.ID.notIn(exclude))));
    }
    public boolean hasFutureScheduled(UUID event, Instant now) {
        return db.fetchExists(db.selectOne()
            .from(EVENT_OCCURRENCES)
            .where(EVENT_OCCURRENCES.EVENT_ID.eq(event)
                .and(EVENT_OCCURRENCES.STATUS.eq("SCHEDULED"))
                .and(EVENT_OCCURRENCES.ENDS_AT.gt(EventRecords.time(now)))));
    }
    public void insertOccurrence(EventOccurrence o) {
        db.insertInto(EVENT_OCCURRENCES)
            .set(EVENT_OCCURRENCES.ID, o.id())
            .set(EVENT_OCCURRENCES.EVENT_ID, o.eventId())
            .set(EVENT_OCCURRENCES.SERIES_ID, o.seriesId())
            .set(EVENT_OCCURRENCES.ORIGIN_DATE, o.originDate())
            .set(EVENT_OCCURRENCES.TIME_ZONE, o.timeZone())
            .set(EVENT_OCCURRENCES.STARTS_AT, EventRecords.time(o.startsAt()))
            .set(EVENT_OCCURRENCES.ENDS_AT, EventRecords.time(o.endsAt()))
            .set(EVENT_OCCURRENCES.VENUE_ID, o.venueId())
            .set(EVENT_OCCURRENCES.VENUE_NAME, o.venueName())
            .set(EVENT_OCCURRENCES.VENUE_ADDRESS, o.venueAddress())
            .set(EVENT_OCCURRENCES.EXCLUSIVE, o.exclusive())
            .set(EVENT_OCCURRENCES.STATUS, o.status())
            .set(EVENT_OCCURRENCES.OVERRIDDEN, o.overridden())
            .set(EVENT_OCCURRENCES.VERSION, o.version())
            .set(EVENT_OCCURRENCES.SCHEDULE_REVISION, o.scheduleRevision())
            .execute();
    }
    public void saveOccurrence(EventOccurrence o) {
        int changed = db.update(EVENT_OCCURRENCES)
            .set(EVENT_OCCURRENCES.STARTS_AT, EventRecords.time(o.startsAt()))
            .set(EVENT_OCCURRENCES.ENDS_AT, EventRecords.time(o.endsAt()))
            .set(EVENT_OCCURRENCES.VENUE_ID, o.venueId())
            .set(EVENT_OCCURRENCES.VENUE_NAME, o.venueName())
            .set(EVENT_OCCURRENCES.VENUE_ADDRESS, o.venueAddress())
            .set(EVENT_OCCURRENCES.EXCLUSIVE, o.exclusive())
            .set(EVENT_OCCURRENCES.STATUS, o.status())
            .set(EVENT_OCCURRENCES.OVERRIDDEN, o.overridden())
            .set(EVENT_OCCURRENCES.VERSION, o.version())
            .set(EVENT_OCCURRENCES.SCHEDULE_REVISION, o.scheduleRevision())
            .where(EVENT_OCCURRENCES.ID.eq(o.id()).and(EVENT_OCCURRENCES.VERSION.eq(o.version() - 1)))
            .execute();
        if (changed != 1) {
            throw EventException.conflict("Occurrence changed. Refresh first.");
        }
    }
}
