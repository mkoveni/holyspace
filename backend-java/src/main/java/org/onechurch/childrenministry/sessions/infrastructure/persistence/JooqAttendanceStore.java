package org.onechurch.childrenministry.sessions.infrastructure.persistence;
import static org.onechurch.childrenministry.shared.infrastructure.persistence.ChildrenRecords.time;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_ATTENDANCE;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_ATTENDANCE_EVENTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_ATTENDANCE_SEGMENTS;

import org.jooq.DSLContext;
import org.onechurch.childrenministry.sessions.application.contract.AttendanceReader;
import org.onechurch.childrenministry.sessions.domain.Attendance;
import org.onechurch.childrenministry.sessions.domain.AttendanceEvent;
import org.onechurch.childrenministry.sessions.domain.AttendanceSegment;
import org.onechurch.childrenministry.sessions.domain.repository.AttendanceRepository;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.infrastructure.persistence.ChildrenRecords;
import org.onechurch.shared.infrastructure.jooq.tables.records.ChildrenAttendanceEventsRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.ChildrenAttendanceRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.ChildrenAttendanceSegmentsRecord;
import org.springframework.stereotype.Repository;

import java.time.Instant;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
@Repository
public class JooqAttendanceStore implements AttendanceReader, AttendanceRepository {
    private final DSLContext db;
    public JooqAttendanceStore(DSLContext db) {
        this.db = db;
    }
    @Override
    public Optional<Attendance> attendance(UUID id) {
        return db.selectFrom(CHILDREN_ATTENDANCE)
            .where(CHILDREN_ATTENDANCE.ID.eq(id))
            .fetchOptional(ChildrenRecords::attendance);
    }
    @Override
    public Optional<Attendance> attendanceForUpdate(UUID id) {
        return db.selectFrom(CHILDREN_ATTENDANCE)
            .where(CHILDREN_ATTENDANCE.ID.eq(id))
            .forUpdate()
            .fetchOptional(ChildrenRecords::attendance);
    }
    @Override
    public void insertAttendance(Attendance value) {
        db.insertInto(CHILDREN_ATTENDANCE).set(record(value)).execute();
    }
    @Override
    public void updateAttendance(Attendance value) {
        int changed = db.update(CHILDREN_ATTENDANCE)
            .set(record(value))
            .where(CHILDREN_ATTENDANCE.ID.eq(value.id()))
            .and(CHILDREN_ATTENDANCE.VERSION.eq(value.version() - 1))
            .execute();
        if (changed != 1) {
            throw ChildrenException.conflict("Record changed. Reload and try again.");
        }
    }
    private ChildrenAttendanceRecord record(Attendance value) {
        ChildrenAttendanceRecord row = db.newRecord(CHILDREN_ATTENDANCE);
        row.setId(value.id());
        row.setChildId(value.childId());
        row.setSessionId(value.sessionId());
        row.setArrivedAt(time(value.arrivedAt()));
        row.setArrivedBy(value.arrivedBy());
        row.setArrivalRequestId(value.arrivalRequestId());
        row.setCheckedOutAt(time(value.checkedOutAt()));
        row.setCheckedOutBy(value.checkedOutBy());
        row.setCollectorId(value.collectorId());
        row.setCheckoutRequestId(value.checkoutRequestId());
        row.setAuthorizationId(value.authorizationId());
        row.setIdentityVerified(value.identityVerified());
        row.setExceptionReason(value.exceptionReason());
        row.setLabelHash(value.labelHash());
        row.setLabelVersion(value.labelVersion());
        row.setLabelExpiresAt(time(value.labelExpiresAt()));
        row.setFailedAttempts(value.failedAttempts());
        row.setAttemptsResetAt(time(value.attemptsResetAt()));
        row.setVersion(value.version());
        return row;
    }
    @Override
    public void insertAttendanceSegment(AttendanceSegment value) {
        db.insertInto(CHILDREN_ATTENDANCE_SEGMENTS).set(record(value)).execute();
    }
    private ChildrenAttendanceSegmentsRecord record(AttendanceSegment value) {
        ChildrenAttendanceSegmentsRecord row = db.newRecord(CHILDREN_ATTENDANCE_SEGMENTS);
        row.setId(value.id());
        row.setAttendanceId(value.attendanceId());
        row.setSessionId(value.sessionId());
        row.setEnrolmentId(value.enrolmentId());
        row.setAssignmentId(value.assignmentId());
        row.setConsentId(value.consentId());
        row.setArrivedAt(time(value.arrivedAt()));
        row.setLeftAt(time(value.leftAt()));
        return row;
    }
    @Override
    public void insertAttendanceEvent(AttendanceEvent value) {
        db.insertInto(CHILDREN_ATTENDANCE_EVENTS).set(record(value)).execute();
    }
    private ChildrenAttendanceEventsRecord record(AttendanceEvent value) {
        ChildrenAttendanceEventsRecord row = db.newRecord(CHILDREN_ATTENDANCE_EVENTS);
        row.setId(value.id());
        row.setAttendanceId(value.attendanceId());
        row.setActorId(value.actorId());
        row.setType(value.type());
        row.setReason(value.reason());
        row.setOccurredAt(time(value.occurredAt()));
        return row;
    }

    public List<Attendance> roster(UUID sessionId, boolean openOnly, int limit, int offset) {
        return db.selectFrom(CHILDREN_ATTENDANCE)
            .where(CHILDREN_ATTENDANCE.SESSION_ID.eq(sessionId))
            .and(openOnly ? CHILDREN_ATTENDANCE.CHECKED_OUT_AT.isNull() : org.jooq.impl.DSL.noCondition())
            .orderBy(CHILDREN_ATTENDANCE.ARRIVED_AT, CHILDREN_ATTENDANCE.ID)
            .limit(limit)
            .offset(offset)
            .fetch(ChildrenRecords::attendance);
    }
    public int occupancy(UUID sessionId) {
        return db.fetchCount(CHILDREN_ATTENDANCE,
            CHILDREN_ATTENDANCE.SESSION_ID.eq(sessionId).and(CHILDREN_ATTENDANCE.CHECKED_OUT_AT.isNull()));
    }
    public Optional<Attendance> arrival(UUID actorId, UUID requestId) {
        return db.selectFrom(CHILDREN_ATTENDANCE)
            .where(CHILDREN_ATTENDANCE.ARRIVED_BY.eq(actorId))
            .and(CHILDREN_ATTENDANCE.ARRIVAL_REQUEST_ID.eq(requestId))
            .fetchOptional(ChildrenRecords::attendance);
    }
    public List<AttendanceEvent> events(UUID attendanceId, int limit, int offset) {
        return db.selectFrom(CHILDREN_ATTENDANCE_EVENTS)
            .where(CHILDREN_ATTENDANCE_EVENTS.ATTENDANCE_ID.eq(attendanceId))
            .orderBy(CHILDREN_ATTENDANCE_EVENTS.OCCURRED_AT, CHILDREN_ATTENDANCE_EVENTS.ID)
            .limit(limit)
            .offset(offset)
            .fetch(ChildrenRecords::attendanceEvent);
    }
    public List<AttendanceSegment> segments(UUID attendanceId) {
        return db.selectFrom(CHILDREN_ATTENDANCE_SEGMENTS)
            .where(CHILDREN_ATTENDANCE_SEGMENTS.ATTENDANCE_ID.eq(attendanceId))
            .orderBy(CHILDREN_ATTENDANCE_SEGMENTS.ARRIVED_AT, CHILDREN_ATTENDANCE_SEGMENTS.ID)
            .fetch(ChildrenRecords::attendanceSegment);
    }
    public List<Attendance> childAttendance(UUID childId, int limit, int offset) {
        return db.selectFrom(CHILDREN_ATTENDANCE)
            .where(CHILDREN_ATTENDANCE.CHILD_ID.eq(childId))
            .orderBy(CHILDREN_ATTENDANCE.ARRIVED_AT.desc(), CHILDREN_ATTENDANCE.ID)
            .limit(limit)
            .offset(offset)
            .fetch(ChildrenRecords::attendance);
    }
    public void closeSegment(UUID attendanceId, Instant at) {
        db.update(CHILDREN_ATTENDANCE_SEGMENTS)
            .set(CHILDREN_ATTENDANCE_SEGMENTS.LEFT_AT, time(at))
            .where(CHILDREN_ATTENDANCE_SEGMENTS.ATTENDANCE_ID.eq(attendanceId))
            .and(CHILDREN_ATTENDANCE_SEGMENTS.LEFT_AT.isNull())
            .execute();
    }

}
