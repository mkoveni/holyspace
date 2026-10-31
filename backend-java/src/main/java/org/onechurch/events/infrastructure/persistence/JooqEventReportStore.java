package org.onechurch.events.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_ATTENDANCE;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_DEFINITIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_OCCURRENCES;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_REGISTRATIONS;

import org.jooq.Condition;
import org.jooq.DSLContext;
import org.jooq.impl.DSL;
import org.onechurch.events.application.contract.AttendanceReader;
import org.onechurch.events.application.contract.EventReportReader;
import org.springframework.stereotype.Repository;

import java.time.Instant;
import java.util.List;
import java.util.UUID;
@Repository
public class JooqEventReportStore implements EventReportReader {
    private final DSLContext db;
    public JooqEventReportStore(DSLContext db) {
        this.db = db;
    }
    private Condition filter(UUID event, String search, Instant from, Instant until) {
        return EVENT_OCCURRENCES.STARTS_AT.lt(EventRecords.time(until))
            .and(EVENT_OCCURRENCES.ENDS_AT.gt(EventRecords.time(from)))
            .and(event == null ? DSL.noCondition() : EVENT_OCCURRENCES.EVENT_ID.eq(event))
            .and(search == null || search.isBlank() ? DSL.noCondition()
                : EVENT_DEFINITIONS.TITLE.containsIgnoreCase(search));
    }
    public List<Row> rows(UUID event, String search, Instant from, Instant until, Instant now, int limit, int offset) {
        return db
            .select(EVENT_OCCURRENCES.ID, EVENT_OCCURRENCES.EVENT_ID, EVENT_DEFINITIONS.TITLE,
                EVENT_OCCURRENCES.STARTS_AT, EVENT_OCCURRENCES.ENDS_AT, EVENT_OCCURRENCES.TIME_ZONE,
                EVENT_OCCURRENCES.VENUE_NAME, EVENT_OCCURRENCES.STATUS,
                JooqAttendanceStore.total(EVENT_REGISTRATIONS.STATUS.eq("CONFIRMED")),
                JooqAttendanceStore.total(EVENT_REGISTRATIONS.STATUS.eq("WAITLISTED")),
                JooqAttendanceStore.total(EVENT_REGISTRATIONS.STATUS.eq("CANCELLED")),
                JooqAttendanceStore.total(JooqAttendanceStore.present()),
                JooqAttendanceStore.total(JooqAttendanceStore.absent(now)))
            .from(EVENT_OCCURRENCES)
            .join(EVENT_DEFINITIONS)
            .on(EVENT_DEFINITIONS.ID.eq(EVENT_OCCURRENCES.EVENT_ID))
            .leftJoin(EVENT_REGISTRATIONS)
            .on(EVENT_REGISTRATIONS.OCCURRENCE_ID.eq(EVENT_OCCURRENCES.ID))
            .leftJoin(EVENT_ATTENDANCE)
            .on(EVENT_ATTENDANCE.REGISTRATION_ID.eq(EVENT_REGISTRATIONS.ID))
            .where(filter(event, search, from, until))
            .groupBy(EVENT_OCCURRENCES.ID, EVENT_OCCURRENCES.EVENT_ID, EVENT_DEFINITIONS.TITLE,
                EVENT_OCCURRENCES.STARTS_AT, EVENT_OCCURRENCES.ENDS_AT, EVENT_OCCURRENCES.TIME_ZONE,
                EVENT_OCCURRENCES.VENUE_NAME, EVENT_OCCURRENCES.STATUS)
            .orderBy(EVENT_OCCURRENCES.STARTS_AT, EVENT_OCCURRENCES.ID)
            .limit(limit)
            .offset(offset)
            .fetch(r -> new Row(r.value1(), r.value2(), r.value3(), r.value4().toInstant(), r.value5().toInstant(),
                r.value6(), r.value7(), r.value8(),
                new AttendanceReader.Counts(r.value9(), r.value10(), r.value11(), r.value12(), r.value13())));
    }
    public Totals totals(UUID event, String search, Instant from, Instant until, Instant now) {
        return db
            .select(DSL.countDistinct(EVENT_OCCURRENCES.ID).cast(Long.class),
                JooqAttendanceStore.total(EVENT_REGISTRATIONS.STATUS.eq("CONFIRMED")),
                JooqAttendanceStore.total(EVENT_REGISTRATIONS.STATUS.eq("WAITLISTED")),
                JooqAttendanceStore.total(EVENT_REGISTRATIONS.STATUS.eq("CANCELLED")),
                JooqAttendanceStore.total(JooqAttendanceStore.present()),
                JooqAttendanceStore.total(JooqAttendanceStore.absent(now)))
            .from(EVENT_OCCURRENCES)
            .join(EVENT_DEFINITIONS)
            .on(EVENT_DEFINITIONS.ID.eq(EVENT_OCCURRENCES.EVENT_ID))
            .leftJoin(EVENT_REGISTRATIONS)
            .on(EVENT_REGISTRATIONS.OCCURRENCE_ID.eq(EVENT_OCCURRENCES.ID))
            .leftJoin(EVENT_ATTENDANCE)
            .on(EVENT_ATTENDANCE.REGISTRATION_ID.eq(EVENT_REGISTRATIONS.ID))
            .where(filter(event, search, from, until))
            .fetchOne(r -> new Totals(r.value1(), r.value2(), r.value3(), r.value4(), r.value5(), r.value6()));
    }
}
