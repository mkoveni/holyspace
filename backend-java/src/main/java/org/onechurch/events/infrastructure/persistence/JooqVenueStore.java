package org.onechurch.events.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_VENUES;

import org.jooq.Condition;
import org.jooq.DSLContext;
import org.jooq.impl.DSL;
import org.onechurch.events.application.contract.VenueReader;
import org.onechurch.events.domain.EventException;
import org.onechurch.events.domain.Venue;
import org.onechurch.events.domain.repository.VenueRepository;
import org.springframework.stereotype.Repository;

import java.util.List;
import java.util.Optional;
import java.util.UUID;
@Repository
public class JooqVenueStore implements VenueReader, VenueRepository {
    private final DSLContext db;
    public JooqVenueStore(DSLContext db) {
        this.db = db;
    }
    private Condition matching(String search, boolean archived) {
        return EVENT_VENUES.NAME.containsIgnoreCase(search)
            .and(archived ? DSL.noCondition() : EVENT_VENUES.ACTIVE.isTrue());
    }
    public Optional<Venue> venue(UUID id) {
        return db.selectFrom(EVENT_VENUES).where(EVENT_VENUES.ID.eq(id)).fetchOptional(EventRecords::venue);
    }
    public Optional<Venue> lockVenue(UUID id) {
        return db.selectFrom(EVENT_VENUES).where(EVENT_VENUES.ID.eq(id)).forUpdate().fetchOptional(EventRecords::venue);
    }
    public List<Venue> venues(String search, boolean archived, int limit, int offset) {
        return db.selectFrom(EVENT_VENUES)
            .where(matching(search, archived))
            .orderBy(EVENT_VENUES.NAME, EVENT_VENUES.ID)
            .limit(limit)
            .offset(offset)
            .fetch(EventRecords::venue);
    }
    public long venueCount(String search, boolean archived) {
        return db.fetchCount(EVENT_VENUES, matching(search, archived));
    }
    public void insertVenue(Venue v) {
        db.insertInto(EVENT_VENUES)
            .set(EVENT_VENUES.ID, v.id())
            .set(EVENT_VENUES.NAME, v.name())
            .set(EVENT_VENUES.ADDRESS, v.address())
            .set(EVENT_VENUES.EXCLUSIVE, v.exclusive())
            .set(EVENT_VENUES.ACTIVE, v.active())
            .set(EVENT_VENUES.VERSION, v.version())
            .execute();
    }
    public void saveVenue(Venue v) {
        int changed = db.update(EVENT_VENUES)
            .set(EVENT_VENUES.NAME, v.name())
            .set(EVENT_VENUES.ADDRESS, v.address())
            .set(EVENT_VENUES.ACTIVE, v.active())
            .set(EVENT_VENUES.VERSION, v.version())
            .where(EVENT_VENUES.ID.eq(v.id()).and(EVENT_VENUES.VERSION.eq(v.version() - 1)))
            .execute();
        if (changed != 1) {
            throw EventException.conflict("Venue changed. Refresh first.");
        }
    }
}
