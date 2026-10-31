package org.onechurch.events.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_REGISTRATIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_REGISTRATION_HISTORY;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_REGISTRATION_POLICIES;

import org.jooq.Condition;
import org.jooq.DSLContext;
import org.jooq.impl.DSL;
import org.onechurch.events.application.contract.RegistrationReader;
import org.onechurch.events.domain.EventException;
import org.onechurch.events.domain.EventRegistration;
import org.onechurch.events.domain.RegistrationPolicy;
import org.onechurch.events.domain.repository.RegistrationRepository;
import org.springframework.stereotype.Repository;

import java.time.Instant;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
@Repository
public class JooqRegistrationStore implements RegistrationReader, RegistrationRepository {
    private final DSLContext db;
    public JooqRegistrationStore(DSLContext db) {
        this.db = db;
    }
    public Optional<RegistrationPolicy> policy(UUID id) {
        return db.selectFrom(EVENT_REGISTRATION_POLICIES)
            .where(EVENT_REGISTRATION_POLICIES.OCCURRENCE_ID.eq(id))
            .fetchOptional(EventRecords::policy);
    }
    public Optional<EventRegistration> registration(UUID id) {
        return db.selectFrom(EVENT_REGISTRATIONS)
            .where(EVENT_REGISTRATIONS.ID.eq(id))
            .fetchOptional(EventRecords::registration);
    }
    public Optional<EventRegistration> forPerson(UUID occurrence, UUID person) {
        return db.selectFrom(EVENT_REGISTRATIONS)
            .where(EVENT_REGISTRATIONS.OCCURRENCE_ID.eq(occurrence).and(EVENT_REGISTRATIONS.PERSON_ID.eq(person)))
            .fetchOptional(EventRecords::registration);
    }
    public Counts counts(UUID occurrence) {
        java.util.Map<String, Long> counts = db.select(EVENT_REGISTRATIONS.STATUS, DSL.count().cast(Long.class))
            .from(EVENT_REGISTRATIONS)
            .where(EVENT_REGISTRATIONS.OCCURRENCE_ID.eq(occurrence))
            .groupBy(EVENT_REGISTRATIONS.STATUS)
            .fetchMap(EVENT_REGISTRATIONS.STATUS, DSL.count().cast(Long.class));
        return new Counts(counts.getOrDefault("CONFIRMED", 0L), counts.getOrDefault("WAITLISTED", 0L),
            counts.getOrDefault("CANCELLED", 0L));
    }
    public java.util.Map<UUID, Counts> countsMany(List<UUID> occurrences) {
        java.util.Map<UUID, java.util.Map<String, Long>> grouped = new java.util.HashMap<>();
        db.select(EVENT_REGISTRATIONS.OCCURRENCE_ID, EVENT_REGISTRATIONS.STATUS, DSL.count())
            .from(EVENT_REGISTRATIONS)
            .where(EVENT_REGISTRATIONS.OCCURRENCE_ID.in(occurrences))
            .groupBy(EVENT_REGISTRATIONS.OCCURRENCE_ID, EVENT_REGISTRATIONS.STATUS)
            .fetch()
            .forEach(r -> grouped.computeIfAbsent(r.value1(), id -> new java.util.HashMap<>())
                .put(r.value2(), r.value3().longValue()));
        java.util.Map<UUID, Counts> result = new java.util.HashMap<>();
        grouped.forEach((id, states) -> result.put(id, new Counts(states.getOrDefault("CONFIRMED", 0L),
            states.getOrDefault("WAITLISTED", 0L), states.getOrDefault("CANCELLED", 0L))));
        return java.util.Map.copyOf(result);
    }
    private Condition matching(UUID occurrence, String status) {
        return EVENT_REGISTRATIONS.OCCURRENCE_ID.eq(occurrence)
            .and(status == null ? DSL.noCondition() : EVENT_REGISTRATIONS.STATUS.eq(status));
    }
    public List<EventRegistration> registrations(UUID occurrence, String status, int limit, int offset) {
        return db.selectFrom(EVENT_REGISTRATIONS)
            .where(matching(occurrence, status))
            .orderBy(EVENT_REGISTRATIONS.QUEUE_POSITION, EVENT_REGISTRATIONS.ID)
            .limit(limit)
            .offset(offset)
            .fetch(EventRecords::registration);
    }
    public long registrationCount(UUID occurrence, String status) {
        return db.fetchCount(EVENT_REGISTRATIONS, matching(occurrence, status));
    }
    public void insertPolicy(RegistrationPolicy p) {
        db.insertInto(EVENT_REGISTRATION_POLICIES)
            .set(EVENT_REGISTRATION_POLICIES.OCCURRENCE_ID, p.occurrenceId())
            .set(EVENT_REGISTRATION_POLICIES.OPEN, p.open())
            .set(EVENT_REGISTRATION_POLICIES.CAPACITY, p.capacity())
            .set(EVENT_REGISTRATION_POLICIES.WAITLIST_ENABLED, p.waitlistEnabled())
            .set(EVENT_REGISTRATION_POLICIES.NEXT_POSITION, p.nextPosition())
            .set(EVENT_REGISTRATION_POLICIES.VERSION, p.version())
            .execute();
    }
    public void savePolicy(RegistrationPolicy p) {
        int changed = db.update(EVENT_REGISTRATION_POLICIES)
            .set(EVENT_REGISTRATION_POLICIES.OPEN, p.open())
            .set(EVENT_REGISTRATION_POLICIES.CAPACITY, p.capacity())
            .set(EVENT_REGISTRATION_POLICIES.WAITLIST_ENABLED, p.waitlistEnabled())
            .set(EVENT_REGISTRATION_POLICIES.NEXT_POSITION, p.nextPosition())
            .set(EVENT_REGISTRATION_POLICIES.VERSION, p.version())
            .where(EVENT_REGISTRATION_POLICIES.OCCURRENCE_ID.eq(p.occurrenceId())
                .and(EVENT_REGISTRATION_POLICIES.VERSION.eq(p.version() - 1)))
            .execute();
        if (changed != 1) {
            throw EventException.conflict("Registration policy changed. Refresh first.");
        }
    }
    public void insertRegistration(EventRegistration r) {
        db.insertInto(EVENT_REGISTRATIONS)
            .set(EVENT_REGISTRATIONS.ID, r.id())
            .set(EVENT_REGISTRATIONS.OCCURRENCE_ID, r.occurrenceId())
            .set(EVENT_REGISTRATIONS.PERSON_ID, r.personId())
            .set(EVENT_REGISTRATIONS.STATUS, r.status())
            .set(EVENT_REGISTRATIONS.QUEUE_POSITION, r.position())
            .set(EVENT_REGISTRATIONS.VERSION, r.version())
            .set(EVENT_REGISTRATIONS.CREATED_AT, EventRecords.time(r.createdAt()))
            .set(EVENT_REGISTRATIONS.UPDATED_AT, EventRecords.time(r.updatedAt()))
            .execute();
    }
    public void saveRegistration(EventRegistration r) {
        int changed = db.update(EVENT_REGISTRATIONS)
            .set(EVENT_REGISTRATIONS.STATUS, r.status())
            .set(EVENT_REGISTRATIONS.QUEUE_POSITION, r.position())
            .set(EVENT_REGISTRATIONS.VERSION, r.version())
            .set(EVENT_REGISTRATIONS.UPDATED_AT, EventRecords.time(r.updatedAt()))
            .where(EVENT_REGISTRATIONS.ID.eq(r.id()).and(EVENT_REGISTRATIONS.VERSION.eq(r.version() - 1)))
            .execute();
        if (changed != 1) {
            throw EventException.conflict("Registration changed. Refresh first.");
        }
    }
    private void transition(org.jooq.Select<org.jooq.Record1<UUID>> ids, String state, UUID actor, String action,
        String reason, Instant now) {

        db.insertInto(EVENT_REGISTRATION_HISTORY, EVENT_REGISTRATION_HISTORY.ID,
            EVENT_REGISTRATION_HISTORY.REGISTRATION_ID, EVENT_REGISTRATION_HISTORY.ACTOR_ID,
            EVENT_REGISTRATION_HISTORY.ACTION, EVENT_REGISTRATION_HISTORY.REASON,
            EVENT_REGISTRATION_HISTORY.OCCURRED_AT)
            .select(db.select(DSL.uuid(), EVENT_REGISTRATIONS.ID, DSL.val(actor), DSL.val(action), DSL.val(reason),
                DSL.val(EventRecords.time(now))).from(EVENT_REGISTRATIONS).where(EVENT_REGISTRATIONS.ID.in(ids)))
            .execute();
        db.update(EVENT_REGISTRATIONS)
            .set(EVENT_REGISTRATIONS.STATUS, state)
            .set(EVENT_REGISTRATIONS.VERSION, EVENT_REGISTRATIONS.VERSION.plus(1))
            .set(EVENT_REGISTRATIONS.UPDATED_AT, EventRecords.time(now))
            .where(EVENT_REGISTRATIONS.ID.in(ids))
            .execute();
    }
    public void cancelOccurrenceRegistrations(UUID occurrence, UUID actor, String reason, Instant now) {
        org.jooq.Select<org.jooq.Record1<UUID>> ids = db.select(EVENT_REGISTRATIONS.ID)
            .from(EVENT_REGISTRATIONS)
            .where(EVENT_REGISTRATIONS.OCCURRENCE_ID.eq(occurrence).and(EVENT_REGISTRATIONS.STATUS.ne("CANCELLED")));
        transition(ids, "CANCELLED", actor, "OCCURRENCE_CANCELLED", reason, now);
    }
    public void promote(UUID occurrence, int places, UUID actor, Instant now) {
        if (places < 1) {
            return;
        }
        org.jooq.Select<org.jooq.Record1<UUID>> ids = db.select(EVENT_REGISTRATIONS.ID)
            .from(EVENT_REGISTRATIONS)
            .where(EVENT_REGISTRATIONS.OCCURRENCE_ID.eq(occurrence).and(EVENT_REGISTRATIONS.STATUS.eq("WAITLISTED")))
            .orderBy(EVENT_REGISTRATIONS.QUEUE_POSITION)
            .limit(places);
        transition(ids, "CONFIRMED", actor, "PROMOTED", "A place became available; promoted in queue order.", now);
    }
}
