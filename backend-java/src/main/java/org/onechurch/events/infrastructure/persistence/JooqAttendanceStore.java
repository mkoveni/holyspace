package org.onechurch.events.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_ATTENDANCE;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_ATTENDANCE_HISTORY;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_OCCURRENCES;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_REGISTRATIONS;

import org.jooq.Condition;
import org.jooq.DSLContext;
import org.jooq.Field;
import org.jooq.impl.DSL;
import org.onechurch.events.application.contract.AttendanceReader;
import org.onechurch.events.domain.Attendance;
import org.onechurch.events.domain.EventException;
import org.onechurch.events.domain.repository.AttendanceRepository;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.shared.infrastructure.jooq.tables.records.EventAttendanceRecord;
import org.springframework.stereotype.Repository;

import java.time.Instant;
import java.util.List;
import java.util.Map;
import java.util.Optional;
import java.util.UUID;
import java.util.stream.Collectors;
@Repository("eventAttendanceStore")
public class JooqAttendanceStore implements AttendanceReader, AttendanceRepository {
    private final DSLContext db;
    private final PersonDirectory people;
    public JooqAttendanceStore(DSLContext db, PersonDirectory people) {
        this.db = db;
        this.people = people;
    }
    static Attendance attendance(EventAttendanceRecord r) {
        return new Attendance(r.getId(), r.getRegistrationId(), r.getArrivedAt().toInstant(), r.getStatus(),
            r.getVersion());
    }
    public Optional<Attendance> forRegistration(UUID id) {
        return db.selectFrom(EVENT_ATTENDANCE)
            .where(EVENT_ATTENDANCE.REGISTRATION_ID.eq(id))
            .fetchOptional(JooqAttendanceStore::attendance);
    }
    public void insert(Attendance a) {
        db.insertInto(EVENT_ATTENDANCE)
            .set(EVENT_ATTENDANCE.ID, a.id())
            .set(EVENT_ATTENDANCE.REGISTRATION_ID, a.registrationId())
            .set(EVENT_ATTENDANCE.ARRIVED_AT, EventRecords.time(a.arrivedAt()))
            .set(EVENT_ATTENDANCE.STATUS, a.status())
            .set(EVENT_ATTENDANCE.VERSION, a.version())
            .execute();
    }
    public void save(Attendance a) {
        int count = db.update(EVENT_ATTENDANCE)
            .set(EVENT_ATTENDANCE.STATUS, a.status())
            .set(EVENT_ATTENDANCE.VERSION, a.version())
            .where(EVENT_ATTENDANCE.ID.eq(a.id()).and(EVENT_ATTENDANCE.VERSION.eq(a.version() - 1)))
            .execute();
        if (count != 1) {
            throw EventException.conflict("Attendance changed. Refresh before correcting it.");
        }
    }
    public void history(UUID attendance, UUID actor, String action, String reason, Instant now) {
        db.insertInto(EVENT_ATTENDANCE_HISTORY)
            .set(EVENT_ATTENDANCE_HISTORY.ID, UUID.randomUUID())
            .set(EVENT_ATTENDANCE_HISTORY.ATTENDANCE_ID, attendance)
            .set(EVENT_ATTENDANCE_HISTORY.ACTOR_ID, actor)
            .set(EVENT_ATTENDANCE_HISTORY.ACTION, action)
            .set(EVENT_ATTENDANCE_HISTORY.REASON, reason)
            .set(EVENT_ATTENDANCE_HISTORY.OCCURRED_AT, EventRecords.time(now))
            .execute();
    }
    public List<History> history(UUID id, int limit, int offset) {
        return db.selectFrom(EVENT_ATTENDANCE_HISTORY)
            .where(EVENT_ATTENDANCE_HISTORY.ATTENDANCE_ID.eq(id))
            .orderBy(EVENT_ATTENDANCE_HISTORY.OCCURRED_AT.desc(), EVENT_ATTENDANCE_HISTORY.ID.desc())
            .limit(limit)
            .offset(offset)
            .fetch(r -> new History(r.getId(), r.getActorId(), r.getAction(), r.getReason(),
                r.getOccurredAt().toInstant()));
    }
    static Condition present() {
        return EVENT_ATTENDANCE.STATUS.eq("PRESENT");
    }
    static Condition absent(Instant now) {
        return EVENT_REGISTRATIONS.STATUS.eq("CONFIRMED")
            .and(EVENT_OCCURRENCES.STATUS.eq("SCHEDULED"))
            .and(EVENT_OCCURRENCES.ENDS_AT.le(EventRecords.time(now)))
            .and(EVENT_ATTENDANCE.STATUS.isNull().or(EVENT_ATTENDANCE.STATUS.ne("PRESENT")));
    }
    static Field<Long> total(Condition condition) {
        return DSL.coalesce(DSL.sum(DSL.when(condition, 1).otherwise(0)).cast(Long.class), 0L);
    }
    private Field<String> state(Instant now) {
        return DSL.when(present(), "PRESENT")
            .when(EVENT_REGISTRATIONS.STATUS.eq("WAITLISTED"), "WAITLISTED")
            .when(EVENT_REGISTRATIONS.STATUS.eq("CANCELLED"), "CANCELLED")
            .when(absent(now), "ABSENT")
            .otherwise("EXPECTED");
    }
    private Condition matching(UUID occurrence, String search, String status, Instant now) {
        return EVENT_REGISTRATIONS.OCCURRENCE_ID.eq(occurrence)
            .and(search == null || search.isBlank() ? DSL.noCondition()
                : EVENT_REGISTRATIONS.PERSON_ID.in(people.matchingIds(search)))
            .and(status == null ? DSL.noCondition() : state(now).eq(status));
    }
    public List<Entry> roster(UUID occurrence, String search, String status, Instant now, int limit, int offset) {
        List<org.jooq.Record> page = db.select(EVENT_REGISTRATIONS.fields())
            .select(EVENT_ATTENDANCE.fields())
            .select(state(now))
            .from(EVENT_REGISTRATIONS)
            .join(EVENT_OCCURRENCES)
            .on(EVENT_OCCURRENCES.ID.eq(EVENT_REGISTRATIONS.OCCURRENCE_ID))
            .leftJoin(EVENT_ATTENDANCE)
            .on(EVENT_ATTENDANCE.REGISTRATION_ID.eq(EVENT_REGISTRATIONS.ID))
            .where(matching(occurrence, search, status, now))
            .orderBy(EVENT_REGISTRATIONS.QUEUE_POSITION, EVENT_REGISTRATIONS.ID)
            .limit(limit)
            .offset(offset)
            .fetch();
        Map<UUID, String> names =
            people.getAll(page.stream().map(r -> r.get(EVENT_REGISTRATIONS.PERSON_ID)).distinct().toList())
                .stream()
                .collect(Collectors.toMap(PersonDirectory.Entry::id, PersonDirectory.Entry::name));
        return page.stream()
            .map(r -> new Entry(EventRecords.registration(r.into(EVENT_REGISTRATIONS)),
                r.get(EVENT_ATTENDANCE.ID) == null ? null : attendance(r.into(EVENT_ATTENDANCE)),
                names.getOrDefault(r.get(EVENT_REGISTRATIONS.PERSON_ID), "Unavailable person"), r.get(state(now))))
            .toList();
    }
    public long rosterCount(UUID occurrence, String search, String status, Instant now) {
        return db.selectCount()
            .from(EVENT_REGISTRATIONS)
            .join(EVENT_OCCURRENCES)
            .on(EVENT_OCCURRENCES.ID.eq(EVENT_REGISTRATIONS.OCCURRENCE_ID))
            .leftJoin(EVENT_ATTENDANCE)
            .on(EVENT_ATTENDANCE.REGISTRATION_ID.eq(EVENT_REGISTRATIONS.ID))
            .where(matching(occurrence, search, status, now))
            .fetchOne(0, long.class);
    }
    public Counts counts(UUID occurrence, Instant now) {
        return db
            .select(total(EVENT_REGISTRATIONS.STATUS.eq("CONFIRMED")),
                total(EVENT_REGISTRATIONS.STATUS.eq("WAITLISTED")), total(EVENT_REGISTRATIONS.STATUS.eq("CANCELLED")),
                total(present()), total(absent(now)))
            .from(EVENT_REGISTRATIONS)
            .join(EVENT_OCCURRENCES)
            .on(EVENT_OCCURRENCES.ID.eq(EVENT_REGISTRATIONS.OCCURRENCE_ID))
            .leftJoin(EVENT_ATTENDANCE)
            .on(EVENT_ATTENDANCE.REGISTRATION_ID.eq(EVENT_REGISTRATIONS.ID))
            .where(EVENT_REGISTRATIONS.OCCURRENCE_ID.eq(occurrence))
            .fetchOne(r -> new Counts(r.value1(), r.value2(), r.value3(), r.value4(), r.value5()));
    }
}
