package org.onechurch.events.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_ATTENDANCE;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_DEFINITIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_MEMBER_OFFERS;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_OCCURRENCES;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_REGISTRATIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_REGISTRATION_POLICIES;

import org.jooq.Condition;
import org.jooq.DSLContext;
import org.jooq.Field;
import org.jooq.Record;
import org.jooq.SelectConditionStep;
import org.jooq.impl.DSL;
import org.onechurch.events.application.contract.MemberEventReader;
import org.onechurch.events.application.contract.MemberEventWriter;
import org.onechurch.shared.infrastructure.jooq.tables.EventRegistrations;
import org.springframework.stereotype.Repository;

import java.time.Instant;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
@Repository
public class JooqMemberEventStore implements MemberEventReader, MemberEventWriter {
    private final DSLContext db;
    private final EventRegistrations confirmed = EVENT_REGISTRATIONS.as("confirmed_bookings");
    private final Field<Integer> confirmedCount = DSL.selectCount()
        .from(confirmed)
        .where(confirmed.OCCURRENCE_ID.eq(EVENT_OCCURRENCES.ID).and(confirmed.STATUS.eq("CONFIRMED")))
        .asField("confirmed_count");
    public JooqMemberEventStore(DSLContext db) {
        this.db = db;
    }
    public Offer offer(UUID event) {
        return db.selectFrom(EVENT_MEMBER_OFFERS)
            .where(EVENT_MEMBER_OFFERS.EVENT_ID.eq(event))
            .fetchOptional(r -> new Offer(r.getEnabled(), r.getVersion()))
            .orElse(new Offer(false, 0));
    }
    public void offer(UUID event, boolean enabled, long version) {
        db.insertInto(EVENT_MEMBER_OFFERS)
            .set(EVENT_MEMBER_OFFERS.EVENT_ID, event)
            .set(EVENT_MEMBER_OFFERS.ENABLED, enabled)
            .set(EVENT_MEMBER_OFFERS.VERSION, version)
            .onConflict(EVENT_MEMBER_OFFERS.EVENT_ID)
            .doUpdate()
            .set(EVENT_MEMBER_OFFERS.ENABLED, enabled)
            .set(EVENT_MEMBER_OFFERS.VERSION, version)
            .execute();
    }
    private Condition published() {
        return EVENT_MEMBER_OFFERS.ENABLED.isTrue()
            .and(EVENT_DEFINITIONS.ACTIVE.isTrue())
            .and(EVENT_OCCURRENCES.STATUS.eq("SCHEDULED"));
    }
    private SelectConditionStep<Record> select(UUID person, Condition condition) {
        return db.select(EVENT_OCCURRENCES.fields())
            .select(EVENT_DEFINITIONS.fields())
            .select(EVENT_REGISTRATION_POLICIES.fields())
            .select(EVENT_REGISTRATIONS.fields())
            .select(EVENT_ATTENDANCE.STATUS, EVENT_MEMBER_OFFERS.ENABLED, confirmedCount)
            .from(EVENT_OCCURRENCES)
            .join(EVENT_DEFINITIONS)
            .on(EVENT_DEFINITIONS.ID.eq(EVENT_OCCURRENCES.EVENT_ID))
            .join(EVENT_REGISTRATION_POLICIES)
            .on(EVENT_REGISTRATION_POLICIES.OCCURRENCE_ID.eq(EVENT_OCCURRENCES.ID))
            .leftJoin(EVENT_MEMBER_OFFERS)
            .on(EVENT_MEMBER_OFFERS.EVENT_ID.eq(EVENT_DEFINITIONS.ID))
            .leftJoin(EVENT_REGISTRATIONS)
            .on(EVENT_REGISTRATIONS.OCCURRENCE_ID.eq(EVENT_OCCURRENCES.ID)
                .and(EVENT_REGISTRATIONS.PERSON_ID.eq(person)))
            .leftJoin(EVENT_ATTENDANCE)
            .on(EVENT_ATTENDANCE.REGISTRATION_ID.eq(EVENT_REGISTRATIONS.ID))
            .where(condition);
    }
    private Item item(Record r) {
        UUID occurrence = r.get(EVENT_OCCURRENCES.ID);
        Integer capacity = r.get(EVENT_REGISTRATION_POLICIES.CAPACITY);
        Integer remaining = null;
        if (capacity != null) {
            remaining = Math.max(0, capacity - r.get(confirmedCount));
        }
        UUID registration = r.get(EVENT_REGISTRATIONS.ID);
        Booking booking = registration == null ? null : new Booking(registration, r.get(EVENT_REGISTRATIONS.STATUS),
            r.get(EVENT_REGISTRATIONS.VERSION), r.get(EVENT_ATTENDANCE.STATUS));
        return new Item(occurrence, r.get(EVENT_DEFINITIONS.ID), r.get(EVENT_DEFINITIONS.TITLE),
            r.get(EVENT_DEFINITIONS.DESCRIPTION), r.get(EVENT_OCCURRENCES.STARTS_AT).toInstant(),
            r.get(EVENT_OCCURRENCES.ENDS_AT).toInstant(), r.get(EVENT_OCCURRENCES.TIME_ZONE),
            r.get(EVENT_OCCURRENCES.VENUE_NAME), r.get(EVENT_OCCURRENCES.VENUE_ADDRESS),
            r.get(EVENT_OCCURRENCES.STATUS),
            Boolean.TRUE.equals(r.get(EVENT_REGISTRATION_POLICIES.OPEN))
                && Boolean.TRUE.equals(r.get(EVENT_DEFINITIONS.ACTIVE))
                && Boolean.TRUE.equals(r.get(EVENT_MEMBER_OFFERS.ENABLED)),
            r.get(EVENT_REGISTRATION_POLICIES.WAITLIST_ENABLED), remaining, booking);
    }
    public List<Item> list(UUID person, Instant from, Instant until, boolean mine, int limit, int offset) {
        Condition available = mine ? EVENT_REGISTRATIONS.ID.isNotNull() : published();
        return select(person,
            available.and(EVENT_OCCURRENCES.ENDS_AT.gt(EventRecords.time(from)))
                .and(EVENT_OCCURRENCES.STARTS_AT.lt(EventRecords.time(until))))
            .orderBy(EVENT_OCCURRENCES.STARTS_AT, EVENT_OCCURRENCES.ID)
            .limit(limit)
            .offset(offset)
            .fetch(this::item);
    }
    public Optional<Item> detail(UUID person, UUID id) {
        return select(person, EVENT_OCCURRENCES.ID.eq(id).and(published().or(EVENT_REGISTRATIONS.ID.isNotNull())))
            .fetchOptional(this::item);
    }
}
