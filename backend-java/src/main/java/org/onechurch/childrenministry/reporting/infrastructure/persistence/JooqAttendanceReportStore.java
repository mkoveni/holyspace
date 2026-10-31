package org.onechurch.childrenministry.reporting.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_ATTENDANCE;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_ATTENDANCE_SEGMENTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_CLASSES;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_LOCATIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_PROGRAMMES;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_ROOMS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_SESSIONS;

import org.jooq.Condition;
import org.jooq.DSLContext;
import org.jooq.Field;
import org.jooq.Table;
import org.jooq.impl.DSL;
import org.onechurch.childrenministry.reporting.application.AttendanceReport;
import org.onechurch.childrenministry.reporting.application.contract.AttendanceReportReader;
import org.onechurch.childrenministry.shared.infrastructure.persistence.ChildrenRecords;
import org.onechurch.shared.infrastructure.jooq.tables.ChildrenAttendanceSegments;
import org.springframework.stereotype.Repository;
import org.springframework.transaction.annotation.Isolation;
import org.springframework.transaction.annotation.Transactional;

import java.util.List;
@Repository
public class JooqAttendanceReportStore implements AttendanceReportReader {
    private final DSLContext db;
    public JooqAttendanceReportStore(DSLContext db) {
        this.db = db;
    }
    @Override
    @Transactional(readOnly = true, isolation = Isolation.REPEATABLE_READ)
    public AttendanceReport read(Criteria criteria, int limit, int offset) {
        ChildrenAttendanceSegments next = CHILDREN_ATTENDANCE_SEGMENTS.as("next_segment");
        Condition moved = DSL.exists(DSL.selectOne()
            .from(next)
            .where(next.ATTENDANCE_ID.eq(CHILDREN_ATTENDANCE.ID))
            .and(next.ARRIVED_AT.gt(CHILDREN_ATTENDANCE_SEGMENTS.ARRIVED_AT)));
        Field<String> outcome = DSL.when(CHILDREN_ATTENDANCE_SEGMENTS.LEFT_AT.isNull(), "ATTENDING")
            .when(moved, "TRANSFERRED")
            .otherwise("COLLECTED");
        Condition exception = outcome.eq("COLLECTED").and(CHILDREN_ATTENDANCE.EXCEPTION_REASON.ne(""));
        Table<?> source = CHILDREN_ATTENDANCE_SEGMENTS.join(CHILDREN_ATTENDANCE)
            .on(CHILDREN_ATTENDANCE.ID.eq(CHILDREN_ATTENDANCE_SEGMENTS.ATTENDANCE_ID))
            .join(CHILDREN_SESSIONS)
            .on(CHILDREN_SESSIONS.ID.eq(CHILDREN_ATTENDANCE_SEGMENTS.SESSION_ID))
            .join(CHILDREN_CLASSES)
            .on(CHILDREN_CLASSES.ID.eq(CHILDREN_SESSIONS.CLASS_ID))
            .join(CHILDREN_PROGRAMMES)
            .on(CHILDREN_PROGRAMMES.ID.eq(CHILDREN_CLASSES.PROGRAMME_ID))
            .join(CHILDREN_ROOMS)
            .on(CHILDREN_ROOMS.ID.eq(CHILDREN_SESSIONS.ROOM_ID))
            .join(CHILDREN_LOCATIONS)
            .on(CHILDREN_LOCATIONS.ID.eq(CHILDREN_ROOMS.LOCATION_ID));
        Condition where = DSL.noCondition();
        if (criteria.from() != null) {
            where = where.and(CHILDREN_ATTENDANCE_SEGMENTS.ARRIVED_AT.ge(ChildrenRecords.time(criteria.from())))
                .and(CHILDREN_ATTENDANCE_SEGMENTS.ARRIVED_AT.lt(ChildrenRecords.time(criteria.until())));
        }
        if (criteria.programmeId() != null) {
            where = where.and(CHILDREN_PROGRAMMES.ID.eq(criteria.programmeId()));
        }
        if (criteria.locationId() != null) {
            where = where.and(CHILDREN_LOCATIONS.ID.eq(criteria.locationId()));
        }
        if (criteria.childId() != null) {
            where = where.and(CHILDREN_ATTENDANCE.CHILD_ID.eq(criteria.childId()));
        }
        if (criteria.matchingIds() != null) {
            where = where.and(CHILDREN_ATTENDANCE.CHILD_ID.in(criteria.matchingIds()));
        }
        if (criteria.attendanceId() != null) {
            where = where.and(CHILDREN_ATTENDANCE.ID.eq(criteria.attendanceId()));
        }
        if (!criteria.outcome().equals("ALL")) {
            where = where.and(criteria.outcome().equals("SUPERVISOR") ? exception : outcome.eq(criteria.outcome()));
        }
        AttendanceReport.Summary summary = db
            .select(DSL.count(), DSL.countDistinct(CHILDREN_ATTENDANCE.CHILD_ID),
                DSL.count().filterWhere(outcome.eq("ATTENDING")), DSL.count().filterWhere(outcome.eq("COLLECTED")),
                DSL.count().filterWhere(outcome.eq("TRANSFERRED")), DSL.count().filterWhere(exception))
            .from(source)
            .where(where)
            .fetchSingle(row -> new AttendanceReport.Summary(row.value1(), row.value2(), row.value3(), row.value4(),
                row.value5(), row.value6()));
        List<AttendanceReport.Visit> visits = db
            .select(CHILDREN_ATTENDANCE_SEGMENTS.ID, CHILDREN_ATTENDANCE.ID, CHILDREN_ATTENDANCE.CHILD_ID,
                CHILDREN_ATTENDANCE_SEGMENTS.SESSION_ID, CHILDREN_PROGRAMMES.ID, CHILDREN_PROGRAMMES.NAME,
                CHILDREN_CLASSES.NAME, CHILDREN_ROOMS.NAME, CHILDREN_LOCATIONS.NAME, CHILDREN_LOCATIONS.TIME_ZONE,
                CHILDREN_ATTENDANCE_SEGMENTS.ARRIVED_AT, CHILDREN_ATTENDANCE_SEGMENTS.LEFT_AT, outcome, exception)
            .from(source)
            .where(where)
            .orderBy(criteria.attendanceId() == null ? CHILDREN_ATTENDANCE_SEGMENTS.ARRIVED_AT.desc()
                : CHILDREN_ATTENDANCE_SEGMENTS.ARRIVED_AT.asc(), CHILDREN_ATTENDANCE_SEGMENTS.ID)
            .limit(limit)
            .offset(offset)
            .fetch(row -> new AttendanceReport.Visit(row.value1(), row.value2(), row.value3(), "", row.value4(),
                row.value5(), row.value6(), row.value7(), row.value8(), row.value9(), row.value10(),
                ChildrenRecords.instant(row.value11()), ChildrenRecords.instant(row.value12()), row.value13(),
                row.value14()));
        return new AttendanceReport(visits, summary, "", null);
    }
}
