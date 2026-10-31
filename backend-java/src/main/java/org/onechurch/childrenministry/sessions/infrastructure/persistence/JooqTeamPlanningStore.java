package org.onechurch.childrenministry.sessions.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_CLASSES;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_LOCATIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_PROGRAMMES;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_ROOMS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_SESSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_SESSION_STAFF;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_WORKERS;

import org.jooq.Condition;
import org.jooq.DSLContext;
import org.jooq.impl.DSL;
import org.onechurch.childrenministry.sessions.application.contract.TeamPlanningReader;
import org.onechurch.childrenministry.sessions.application.contract.TeamPlanningWriter;
import org.onechurch.childrenministry.sessions.domain.SessionStaff;
import org.onechurch.childrenministry.sessions.domain.Worker;
import org.onechurch.childrenministry.shared.infrastructure.persistence.ChildrenRecords;
import org.springframework.stereotype.Repository;

import java.time.Instant;
import java.util.Collection;
import java.util.HashSet;
import java.util.List;
import java.util.Set;
import java.util.UUID;
@Repository
public class JooqTeamPlanningStore implements TeamPlanningReader, TeamPlanningWriter {
    private final DSLContext db;
    public JooqTeamPlanningStore(DSLContext db) {
        this.db = db;
    }
    @Override
    public List<Session> upcoming(Instant from, Instant until, UUID programmeId, UUID locationId, Instant now) {
        Condition where = CHILDREN_SESSIONS.STARTS_AT.ge(ChildrenRecords.time(from))
            .and(CHILDREN_SESSIONS.STARTS_AT.lt(ChildrenRecords.time(until)))
            .and(CHILDREN_SESSIONS.STARTS_AT.gt(ChildrenRecords.time(now)))
            .and(CHILDREN_SESSIONS.STATUS.notIn("CLOSED", "CANCELLED"));
        if (programmeId != null) {
            where = where.and(CHILDREN_CLASSES.PROGRAMME_ID.eq(programmeId));
        }
        if (locationId != null) {
            where = where.and(CHILDREN_ROOMS.LOCATION_ID.eq(locationId));
        }
        return sessions(where, 1001);
    }
    @Override
    public List<Session> sessions(Collection<UUID> ids) {
        return sessions(CHILDREN_SESSIONS.ID.in(ids), 25);
    }
    private List<Session> sessions(Condition where, int limit) {
        return db.select(CHILDREN_SESSIONS.fields())
            .select(CHILDREN_PROGRAMMES.ID, CHILDREN_PROGRAMMES.NAME, CHILDREN_CLASSES.NAME, CHILDREN_ROOMS.NAME,
                CHILDREN_ROOMS.LOCATION_ID, CHILDREN_LOCATIONS.TIME_ZONE, CHILDREN_ROOMS.CAPACITY,
                CHILDREN_PROGRAMMES.MIN_STAFF, CHILDREN_PROGRAMMES.CHILDREN_PER_STAFF)
            .from(CHILDREN_SESSIONS)
            .join(CHILDREN_CLASSES)
            .on(CHILDREN_CLASSES.ID.eq(CHILDREN_SESSIONS.CLASS_ID))
            .join(CHILDREN_PROGRAMMES)
            .on(CHILDREN_PROGRAMMES.ID.eq(CHILDREN_CLASSES.PROGRAMME_ID))
            .join(CHILDREN_ROOMS)
            .on(CHILDREN_ROOMS.ID.eq(CHILDREN_SESSIONS.ROOM_ID))
            .join(CHILDREN_LOCATIONS)
            .on(CHILDREN_LOCATIONS.ID.eq(CHILDREN_ROOMS.LOCATION_ID))
            .where(where)
            .orderBy(CHILDREN_SESSIONS.STARTS_AT, CHILDREN_SESSIONS.ID)
            .limit(limit)
            .fetch(row -> new Session(ChildrenRecords.ministrySession(row.into(CHILDREN_SESSIONS)),
                row.get(CHILDREN_PROGRAMMES.ID), row.get(CHILDREN_PROGRAMMES.NAME), row.get(CHILDREN_CLASSES.NAME),
                row.get(CHILDREN_ROOMS.NAME), row.get(CHILDREN_ROOMS.LOCATION_ID),
                row.get(CHILDREN_LOCATIONS.TIME_ZONE), row.get(CHILDREN_ROOMS.CAPACITY),
                row.get(CHILDREN_PROGRAMMES.MIN_STAFF), row.get(CHILDREN_PROGRAMMES.CHILDREN_PER_STAFF)));
    }
    @Override
    public Facts facts(List<Session> sessions, Set<UUID> extraWorkers) {
        if (sessions.isEmpty()) {
            return new Facts(List.of(), List.of(), List.of());
        }
        List<UUID> ids = sessions.stream().map(row -> row.value().id()).toList();
        List<SessionStaff> staff = db.selectFrom(CHILDREN_SESSION_STAFF)
            .where(CHILDREN_SESSION_STAFF.SESSION_ID.in(ids))
            .fetch(ChildrenRecords::sessionStaff);
        Set<UUID> workers = new HashSet<>(extraWorkers);
        staff.forEach(member -> workers.add(member.workerId()));
        List<Worker> registered = db.selectFrom(CHILDREN_WORKERS)
            .where(CHILDREN_WORKERS.PERSON_ID.in(workers))
            .fetch(ChildrenRecords::worker);
        Instant from = sessions.stream().map(row -> row.value().startsAt()).min(Instant::compareTo).orElseThrow();
        Instant until = sessions.stream().map(row -> row.value().endsAt()).max(Instant::compareTo).orElseThrow();
        List<Booking> bookings = db
            .select(CHILDREN_SESSION_STAFF.SESSION_ID, CHILDREN_SESSION_STAFF.WORKER_ID, CHILDREN_SESSIONS.STARTS_AT,
                CHILDREN_SESSIONS.ENDS_AT)
            .from(CHILDREN_SESSION_STAFF)
            .join(CHILDREN_SESSIONS)
            .on(CHILDREN_SESSIONS.ID.eq(CHILDREN_SESSION_STAFF.SESSION_ID))
            .where(CHILDREN_SESSION_STAFF.WORKER_ID.in(workers))
            .and(CHILDREN_SESSIONS.STATUS.notIn("CLOSED", "CANCELLED"))
            .and(CHILDREN_SESSIONS.STARTS_AT.lt(ChildrenRecords.time(until)))
            .and(CHILDREN_SESSIONS.ENDS_AT.gt(ChildrenRecords.time(from)))
            .fetch(row -> new Booking(row.value1(), row.value2(), ChildrenRecords.instant(row.value3()),
                ChildrenRecords.instant(row.value4())));
        return new Facts(staff, registered, bookings);
    }
    @Override
    public Workers workers(Collection<UUID> matchingIds, int limit, int offset) {
        Condition where = matchingIds == null ? DSL.noCondition() : CHILDREN_WORKERS.PERSON_ID.in(matchingIds);
        return new Workers(db.selectFrom(CHILDREN_WORKERS)
            .where(where)
            .orderBy(CHILDREN_WORKERS.PERSON_ID)
            .limit(limit)
            .offset(offset)
            .fetch(ChildrenRecords::worker), db.fetchCount(CHILDREN_WORKERS, where));
    }
    @Override
    public void removeAssignment(UUID sessionId, UUID workerId) {
        db.deleteFrom(CHILDREN_SESSION_STAFF)
            .where(CHILDREN_SESSION_STAFF.SESSION_ID.eq(sessionId))
            .and(CHILDREN_SESSION_STAFF.WORKER_ID.eq(workerId))
            .execute();
    }
}
