package org.onechurch.childrenministry.sessions.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_ATTENDANCE;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_CLASSES;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_CLASS_ASSIGNMENTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_ENROLMENTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_GUARDIAN_AUTHORITIES;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_LOCATIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_MEMBER_SESSION_DATES;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_PROGRAMMES;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_ROOMS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_SESSIONS;

import org.jooq.Condition;
import org.jooq.DSLContext;
import org.jooq.impl.DSL;
import org.onechurch.childrenministry.sessions.application.contract.MemberFamilyReader;
import org.onechurch.childrenministry.shared.infrastructure.persistence.ChildrenRecords;
import org.springframework.stereotype.Repository;

import java.time.Instant;
import java.time.LocalDate;
import java.util.List;
import java.util.UUID;
@Repository
public class JooqMemberFamilyStore implements MemberFamilyReader {
    private final DSLContext db;
    public JooqMemberFamilyStore(DSLContext db) {
        this.db = db;
    }
    private Condition authority(UUID guardian, Instant at) {
        return CHILDREN_GUARDIAN_AUTHORITIES.GUARDIAN_ID.eq(guardian)
            .and(CHILDREN_GUARDIAN_AUTHORITIES.REVOKED_AT.isNull())
            .and(CHILDREN_GUARDIAN_AUTHORITIES.VALID_FROM.le(ChildrenRecords.time(at)))
            .and(CHILDREN_GUARDIAN_AUTHORITIES.VALID_UNTIL.gt(ChildrenRecords.time(at)));
    }
    @Override
    public List<UUID> children(UUID guardian, Instant at, int limit, int offset) {
        return db.selectDistinct(CHILDREN_GUARDIAN_AUTHORITIES.CHILD_ID)
            .from(CHILDREN_GUARDIAN_AUTHORITIES)
            .where(authority(guardian, at))
            .orderBy(CHILDREN_GUARDIAN_AUTHORITIES.CHILD_ID)
            .limit(limit)
            .offset(offset)
            .fetch(CHILDREN_GUARDIAN_AUTHORITIES.CHILD_ID);
    }
    @Override
    public boolean authorized(UUID guardian, UUID child, Instant at) {
        return db.fetchExists(db.selectOne()
            .from(CHILDREN_GUARDIAN_AUTHORITIES)
            .where(authority(guardian, at))
            .and(CHILDREN_GUARDIAN_AUTHORITIES.CHILD_ID.eq(child)));
    }
    @Override
    public List<Programme> programmes(UUID child, LocalDate on, int limit, int offset) {
        return db
            .select(CHILDREN_PROGRAMMES.ID, CHILDREN_PROGRAMMES.NAME, CHILDREN_ENROLMENTS.VALID_FROM,
                CHILDREN_ENROLMENTS.VALID_UNTIL)
            .from(CHILDREN_ENROLMENTS)
            .join(CHILDREN_PROGRAMMES)
            .on(CHILDREN_PROGRAMMES.ID.eq(CHILDREN_ENROLMENTS.PROGRAMME_ID))
            .where(CHILDREN_ENROLMENTS.CHILD_ID.eq(child))
            .and(CHILDREN_ENROLMENTS.ENDED_AT.isNull())
            .and(CHILDREN_ENROLMENTS.VALID_UNTIL.gt(on))
            .orderBy(CHILDREN_ENROLMENTS.VALID_FROM, CHILDREN_ENROLMENTS.ID)
            .limit(limit)
            .offset(offset)
            .fetch(r -> new Programme(r.value1(), r.value2(), r.value3(), r.value4()));
    }
    @Override
    public List<Session> sessions(UUID child, Instant from, Instant until, int limit, int offset) {
        return db.select(CHILDREN_SESSIONS.fields())
            .select(CHILDREN_PROGRAMMES.NAME, CHILDREN_CLASSES.NAME, CHILDREN_ROOMS.NAME, CHILDREN_LOCATIONS.NAME,
                CHILDREN_LOCATIONS.TIME_ZONE)
            .from(CHILDREN_SESSIONS)
            .join(CHILDREN_MEMBER_SESSION_DATES)
            .on(CHILDREN_MEMBER_SESSION_DATES.SESSION_ID.eq(CHILDREN_SESSIONS.ID))
            .join(CHILDREN_CLASSES)
            .on(CHILDREN_CLASSES.ID.eq(CHILDREN_SESSIONS.CLASS_ID))
            .join(CHILDREN_PROGRAMMES)
            .on(CHILDREN_PROGRAMMES.ID.eq(CHILDREN_CLASSES.PROGRAMME_ID))
            .join(CHILDREN_ROOMS)
            .on(CHILDREN_ROOMS.ID.eq(CHILDREN_SESSIONS.ROOM_ID))
            .join(CHILDREN_LOCATIONS)
            .on(CHILDREN_LOCATIONS.ID.eq(CHILDREN_ROOMS.LOCATION_ID))
            .where(CHILDREN_SESSIONS.ENDS_AT.gt(ChildrenRecords.time(from)))
            .and(CHILDREN_SESSIONS.STARTS_AT.lt(ChildrenRecords.time(until)))
            .andExists(db.selectOne()
                .from(CHILDREN_CLASS_ASSIGNMENTS)
                .join(CHILDREN_ENROLMENTS)
                .on(CHILDREN_ENROLMENTS.ID.eq(CHILDREN_CLASS_ASSIGNMENTS.ENROLMENT_ID))
                .where(CHILDREN_CLASS_ASSIGNMENTS.CHILD_ID.eq(child))
                .and(CHILDREN_CLASS_ASSIGNMENTS.CLASS_ID.eq(CHILDREN_SESSIONS.CLASS_ID))
                .and(CHILDREN_CLASS_ASSIGNMENTS.ENDED_AT.isNull())
                .and(CHILDREN_ENROLMENTS.ENDED_AT.isNull())
                .and(CHILDREN_CLASS_ASSIGNMENTS.VALID_FROM.le(CHILDREN_MEMBER_SESSION_DATES.ON_DATE))
                .and(CHILDREN_CLASS_ASSIGNMENTS.VALID_UNTIL.gt(CHILDREN_MEMBER_SESSION_DATES.ON_DATE))
                .and(CHILDREN_ENROLMENTS.VALID_FROM.le(CHILDREN_MEMBER_SESSION_DATES.ON_DATE))
                .and(CHILDREN_ENROLMENTS.VALID_UNTIL.gt(CHILDREN_MEMBER_SESSION_DATES.ON_DATE)))
            .orderBy(CHILDREN_SESSIONS.STARTS_AT, CHILDREN_SESSIONS.ID)
            .limit(limit)
            .offset(offset)
            .fetch(r -> new Session(ChildrenRecords.ministrySession(r.into(CHILDREN_SESSIONS)),
                r.get(CHILDREN_PROGRAMMES.NAME), r.get(CHILDREN_CLASSES.NAME), r.get(CHILDREN_ROOMS.NAME),
                r.get(CHILDREN_LOCATIONS.NAME), r.get(CHILDREN_LOCATIONS.TIME_ZONE)));
    }
    @Override
    public List<FamilyAttendance> attendance(UUID guardian, UUID child, Instant at, int limit, int offset) {
        return db.select(CHILDREN_ATTENDANCE.fields())
            .select(CHILDREN_PROGRAMMES.NAME, CHILDREN_CLASSES.NAME, CHILDREN_ROOMS.NAME, CHILDREN_LOCATIONS.NAME,
                CHILDREN_LOCATIONS.TIME_ZONE)
            .from(CHILDREN_ATTENDANCE)
            .join(CHILDREN_SESSIONS)
            .on(CHILDREN_SESSIONS.ID.eq(CHILDREN_ATTENDANCE.SESSION_ID))
            .join(CHILDREN_CLASSES)
            .on(CHILDREN_CLASSES.ID.eq(CHILDREN_SESSIONS.CLASS_ID))
            .join(CHILDREN_PROGRAMMES)
            .on(CHILDREN_PROGRAMMES.ID.eq(CHILDREN_CLASSES.PROGRAMME_ID))
            .join(CHILDREN_ROOMS)
            .on(CHILDREN_ROOMS.ID.eq(CHILDREN_SESSIONS.ROOM_ID))
            .join(CHILDREN_LOCATIONS)
            .on(CHILDREN_LOCATIONS.ID.eq(CHILDREN_ROOMS.LOCATION_ID))
            .where(CHILDREN_ATTENDANCE.CHILD_ID.eq(child))
            .and(CHILDREN_ATTENDANCE.ARRIVED_AT.ge(db.select(DSL.min(CHILDREN_GUARDIAN_AUTHORITIES.VALID_FROM))
                .from(CHILDREN_GUARDIAN_AUTHORITIES)
                .where(authority(guardian, at))
                .and(CHILDREN_GUARDIAN_AUTHORITIES.CHILD_ID.eq(child))))
            .orderBy(CHILDREN_ATTENDANCE.ARRIVED_AT.desc(), CHILDREN_ATTENDANCE.ID)
            .limit(limit)
            .offset(offset)
            .fetch(r -> new FamilyAttendance(r.get(CHILDREN_ATTENDANCE.ID), r.get(CHILDREN_ATTENDANCE.SESSION_ID),
                r.get(CHILDREN_PROGRAMMES.NAME), r.get(CHILDREN_CLASSES.NAME), r.get(CHILDREN_ROOMS.NAME),
                r.get(CHILDREN_LOCATIONS.NAME), r.get(CHILDREN_LOCATIONS.TIME_ZONE),
                ChildrenRecords.instant(r.get(CHILDREN_ATTENDANCE.ARRIVED_AT)),
                ChildrenRecords.instant(r.get(CHILDREN_ATTENDANCE.CHECKED_OUT_AT)), r.get(CHILDREN_ATTENDANCE.VERSION),
                r.get(CHILDREN_ATTENDANCE.LABEL_HASH) != null));
    }
}
