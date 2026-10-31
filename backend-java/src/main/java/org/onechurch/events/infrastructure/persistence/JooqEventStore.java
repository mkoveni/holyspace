package org.onechurch.events.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_DEFINITIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_SERIES;

import org.jooq.Condition;
import org.jooq.DSLContext;
import org.jooq.impl.DSL;
import org.onechurch.events.application.contract.EventReader;
import org.onechurch.events.domain.EventDefinition;
import org.onechurch.events.domain.EventException;
import org.onechurch.events.domain.EventSeries;
import org.onechurch.events.domain.repository.EventRepository;
import org.springframework.stereotype.Repository;

import java.util.List;
import java.util.Optional;
import java.util.UUID;
@Repository
public class JooqEventStore implements EventReader, EventRepository {
    private final DSLContext db;
    public JooqEventStore(DSLContext db) {
        this.db = db;
    }
    public List<EventDefinition> eventsByIds(List<UUID> ids) {
        return db.selectFrom(EVENT_DEFINITIONS).where(EVENT_DEFINITIONS.ID.in(ids)).fetch(EventRecords::event);
    }
    public long seriesCount(UUID id) {
        return db.fetchCount(EVENT_SERIES, EVENT_SERIES.EVENT_ID.eq(id));
    }
    public Optional<EventDefinition> event(UUID id) {
        return db.selectFrom(EVENT_DEFINITIONS).where(EVENT_DEFINITIONS.ID.eq(id)).fetchOptional(EventRecords::event);
    }
    public Optional<EventDefinition> lockEvent(UUID id) {
        return db.selectFrom(EVENT_DEFINITIONS)
            .where(EVENT_DEFINITIONS.ID.eq(id))
            .forUpdate()
            .fetchOptional(EventRecords::event);
    }
    private Condition matching(String search, boolean archived) {
        return EVENT_DEFINITIONS.TITLE.containsIgnoreCase(search)
            .and(archived ? DSL.noCondition() : EVENT_DEFINITIONS.ACTIVE.isTrue());
    }
    public List<EventDefinition> events(String search, boolean archived, int limit, int offset) {
        return db.selectFrom(EVENT_DEFINITIONS)
            .where(matching(search, archived))
            .orderBy(EVENT_DEFINITIONS.TITLE, EVENT_DEFINITIONS.ID)
            .limit(limit)
            .offset(offset)
            .fetch(EventRecords::event);
    }
    public long eventCount(String search, boolean archived) {
        return db.fetchCount(EVENT_DEFINITIONS, matching(search, archived));
    }
    public Optional<EventSeries> series(UUID id) {
        return db.selectFrom(EVENT_SERIES).where(EVENT_SERIES.ID.eq(id)).fetchOptional(EventRecords::series);
    }
    public Optional<EventSeries> lockSeries(UUID id) {
        return db.selectFrom(EVENT_SERIES)
            .where(EVENT_SERIES.ID.eq(id))
            .forUpdate()
            .fetchOptional(EventRecords::series);
    }
    public List<EventSeries> seriesFor(UUID id, int limit, int offset) {
        return db.selectFrom(EVENT_SERIES)
            .where(EVENT_SERIES.EVENT_ID.eq(id))
            .orderBy(EVENT_SERIES.FIRST_DATE, EVENT_SERIES.ID)
            .limit(limit)
            .offset(offset)
            .fetch(EventRecords::series);
    }
    public void insertEvent(EventDefinition e) {
        db.insertInto(EVENT_DEFINITIONS)
            .set(EVENT_DEFINITIONS.ID, e.id())
            .set(EVENT_DEFINITIONS.TITLE, e.title())
            .set(EVENT_DEFINITIONS.TYPE, e.type())
            .set(EVENT_DEFINITIONS.DESCRIPTION, e.description())
            .set(EVENT_DEFINITIONS.ACTIVE, e.active())
            .set(EVENT_DEFINITIONS.VERSION, e.version())
            .execute();
    }
    public void saveEvent(EventDefinition e) {
        int changed = db.update(EVENT_DEFINITIONS)
            .set(EVENT_DEFINITIONS.TITLE, e.title())
            .set(EVENT_DEFINITIONS.TYPE, e.type())
            .set(EVENT_DEFINITIONS.DESCRIPTION, e.description())
            .set(EVENT_DEFINITIONS.ACTIVE, e.active())
            .set(EVENT_DEFINITIONS.VERSION, e.version())
            .where(EVENT_DEFINITIONS.ID.eq(e.id()).and(EVENT_DEFINITIONS.VERSION.eq(e.version() - 1)))
            .execute();
        if (changed != 1) {
            throw EventException.conflict("Event changed. Refresh first.");
        }
    }
    public void insertSeries(EventSeries s) {
        db.insertInto(EVENT_SERIES)
            .set(EVENT_SERIES.ID, s.id())
            .set(EVENT_SERIES.EVENT_ID, s.eventId())
            .set(EVENT_SERIES.TIME_ZONE, s.timeZone())
            .set(EVENT_SERIES.FREQUENCY, s.frequency())
            .set(EVENT_SERIES.REPEAT_INTERVAL, s.interval())
            .set(EVENT_SERIES.OCCURRENCE_COUNT, s.count())
            .set(EVENT_SERIES.FIRST_DATE, s.firstDate())
            .set(EVENT_SERIES.STARTS_AT, s.startsAt())
            .set(EVENT_SERIES.ENDS_AT, s.endsAt())
            .set(EVENT_SERIES.END_DAY_OFFSET, s.endDayOffset())
            .set(EVENT_SERIES.VENUE_ID, s.venueId())
            .set(EVENT_SERIES.VERSION, s.version())
            .execute();
    }
    public void saveSeries(EventSeries s) {
        int changed = db.update(EVENT_SERIES)
            .set(EVENT_SERIES.STARTS_AT, s.startsAt())
            .set(EVENT_SERIES.ENDS_AT, s.endsAt())
            .set(EVENT_SERIES.END_DAY_OFFSET, s.endDayOffset())
            .set(EVENT_SERIES.VENUE_ID, s.venueId())
            .set(EVENT_SERIES.VERSION, s.version())
            .where(EVENT_SERIES.ID.eq(s.id()).and(EVENT_SERIES.VERSION.eq(s.version() - 1)))
            .execute();
        if (changed != 1) {
            throw EventException.conflict("Series changed. Refresh first.");
        }
    }
}
