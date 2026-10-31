package org.onechurch.childrenministry.sessions.infrastructure.persistence;
import static org.onechurch.childrenministry.shared.infrastructure.persistence.ChildrenRecords.time;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_LOCATIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_ROOMS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_SESSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_SESSION_STAFF;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_WORKERS;

import org.jooq.DSLContext;
import org.onechurch.childrenministry.participation.domain.Location;
import org.onechurch.childrenministry.participation.domain.Room;
import org.onechurch.childrenministry.sessions.application.contract.SessionReader;
import org.onechurch.childrenministry.sessions.domain.MinistrySession;
import org.onechurch.childrenministry.sessions.domain.SessionStaff;
import org.onechurch.childrenministry.sessions.domain.Worker;
import org.onechurch.childrenministry.sessions.domain.repository.SessionRepository;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.infrastructure.persistence.ChildrenRecords;
import org.onechurch.shared.infrastructure.jooq.tables.records.ChildrenLocationsRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.ChildrenRoomsRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.ChildrenSessionStaffRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.ChildrenSessionsRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.ChildrenWorkersRecord;
import org.springframework.stereotype.Repository;

import java.time.Instant;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
@Repository
public class JooqChildrenSessionStore implements SessionReader, SessionRepository {
    @Override
    public List<MinistrySession> stationSessions(UUID accountId, Instant from, Instant until, int limit, int offset) {
        return db.select(CHILDREN_SESSIONS.fields())
            .from(CHILDREN_SESSIONS)
            .join(CHILDREN_SESSION_STAFF)
            .on(CHILDREN_SESSION_STAFF.SESSION_ID.eq(CHILDREN_SESSIONS.ID))
            .join(CHILDREN_WORKERS)
            .on(CHILDREN_WORKERS.PERSON_ID.eq(CHILDREN_SESSION_STAFF.WORKER_ID))
            .where(CHILDREN_WORKERS.ACCOUNT_ID.eq(accountId))
            .and(CHILDREN_WORKERS.REVOKED_AT.isNull())
            .and(CHILDREN_SESSION_STAFF.PRESENT.isTrue())
            .and(CHILDREN_SESSIONS.STARTS_AT.lt(time(until)))
            .and(CHILDREN_SESSIONS.ENDS_AT.gt(time(from)))
            .orderBy(CHILDREN_SESSIONS.STARTS_AT, CHILDREN_SESSIONS.ID)
            .limit(limit)
            .offset(offset)
            .fetch(row -> ChildrenRecords.ministrySession(row.into(CHILDREN_SESSIONS)));
    }
    @Override
    public boolean presentElsewhere(UUID workerId, UUID sessionId, Instant from, Instant until) {
        return db.fetchExists(db.selectOne()
            .from(CHILDREN_SESSION_STAFF)
            .join(CHILDREN_SESSIONS)
            .on(CHILDREN_SESSIONS.ID.eq(CHILDREN_SESSION_STAFF.SESSION_ID))
            .where(CHILDREN_SESSION_STAFF.WORKER_ID.eq(workerId))
            .and(CHILDREN_SESSION_STAFF.PRESENT.isTrue())
            .and(CHILDREN_SESSIONS.ID.ne(sessionId))
            .and(CHILDREN_SESSIONS.STATUS.notIn("CLOSED", "CANCELLED"))
            .and(CHILDREN_SESSIONS.STARTS_AT.lt(time(until)))
            .and(CHILDREN_SESSIONS.ENDS_AT.gt(time(from))));
    }
    @Override
    public List<Location> locations(int limit, int offset) {
        return db.selectFrom(CHILDREN_LOCATIONS)
            .orderBy(CHILDREN_LOCATIONS.NAME, CHILDREN_LOCATIONS.ID)
            .limit(limit)
            .offset(offset)
            .fetch(ChildrenRecords::location);
    }

    private final DSLContext db;
    public JooqChildrenSessionStore(DSLContext db) {
        this.db = db;
    }
    @Override
    public Optional<Location> location(UUID id) {
        return db.selectFrom(CHILDREN_LOCATIONS)
            .where(CHILDREN_LOCATIONS.ID.eq(id))
            .fetchOptional(ChildrenRecords::location);
    }
    @Override
    public Optional<Location> locationForUpdate(UUID id) {
        return db.selectFrom(CHILDREN_LOCATIONS)
            .where(CHILDREN_LOCATIONS.ID.eq(id))
            .forUpdate()
            .fetchOptional(ChildrenRecords::location);
    }
    @Override
    public void insertLocation(Location value) {
        db.insertInto(CHILDREN_LOCATIONS).set(record(value)).execute();
    }
    private ChildrenLocationsRecord record(Location value) {
        ChildrenLocationsRecord row = db.newRecord(CHILDREN_LOCATIONS);
        row.setId(value.id());
        row.setName(value.name());
        row.setTimeZone(value.timeZone());
        return row;
    }
    @Override
    public Optional<Room> room(UUID id) {
        return db.selectFrom(CHILDREN_ROOMS).where(CHILDREN_ROOMS.ID.eq(id)).fetchOptional(ChildrenRecords::room);
    }
    @Override
    public Optional<Room> roomForUpdate(UUID id) {
        return db.selectFrom(CHILDREN_ROOMS)
            .where(CHILDREN_ROOMS.ID.eq(id))
            .forUpdate()
            .fetchOptional(ChildrenRecords::room);
    }
    @Override
    public void insertRoom(Room value) {
        db.insertInto(CHILDREN_ROOMS).set(record(value)).execute();
    }
    private ChildrenRoomsRecord record(Room value) {
        ChildrenRoomsRecord row = db.newRecord(CHILDREN_ROOMS);
        row.setId(value.id());
        row.setLocationId(value.locationId());
        row.setName(value.name());
        row.setCapacity(value.capacity());
        return row;
    }
    @Override
    public Optional<Worker> worker(UUID id) {
        return db.selectFrom(CHILDREN_WORKERS)
            .where(CHILDREN_WORKERS.PERSON_ID.eq(id))
            .fetchOptional(ChildrenRecords::worker);
    }
    @Override
    public Optional<Worker> workerForUpdate(UUID id) {
        return db.selectFrom(CHILDREN_WORKERS)
            .where(CHILDREN_WORKERS.PERSON_ID.eq(id))
            .forUpdate()
            .fetchOptional(ChildrenRecords::worker);
    }
    @Override
    public void insertWorker(Worker value) {
        db.insertInto(CHILDREN_WORKERS).set(record(value)).execute();
    }
    @Override
    public void updateWorker(Worker value) {
        int changed = db.update(CHILDREN_WORKERS)
            .set(record(value))
            .where(CHILDREN_WORKERS.PERSON_ID.eq(value.personId()))
            .and(CHILDREN_WORKERS.VERSION.eq(value.version() - 1))
            .execute();
        if (changed != 1) {
            throw ChildrenException.conflict("Record changed. Reload and try again.");
        }
    }
    private ChildrenWorkersRecord record(Worker value) {
        ChildrenWorkersRecord row = db.newRecord(CHILDREN_WORKERS);
        row.setPersonId(value.personId());
        row.setAccountId(value.accountId());
        row.setVerifiedFrom(time(value.verifiedFrom()));
        row.setScreenedUntil(time(value.screenedUntil()));
        row.setTrainedUntil(time(value.trainedUntil()));
        row.setEvidenceReference(value.evidenceReference());
        row.setVerifiedBy(value.verifiedBy());
        row.setRevokedAt(time(value.revokedAt()));
        row.setVersion(value.version());
        return row;
    }
    @Override
    public Optional<MinistrySession> ministrySession(UUID id) {
        return db.selectFrom(CHILDREN_SESSIONS)
            .where(CHILDREN_SESSIONS.ID.eq(id))
            .fetchOptional(ChildrenRecords::ministrySession);
    }
    @Override
    public Optional<MinistrySession> ministrySessionForUpdate(UUID id) {
        return db.selectFrom(CHILDREN_SESSIONS)
            .where(CHILDREN_SESSIONS.ID.eq(id))
            .forUpdate()
            .fetchOptional(ChildrenRecords::ministrySession);
    }
    @Override
    public void insertMinistrySession(MinistrySession value) {
        db.insertInto(CHILDREN_SESSIONS).set(record(value)).execute();
    }
    @Override
    public void updateMinistrySession(MinistrySession value) {
        int changed = db.update(CHILDREN_SESSIONS)
            .set(record(value))
            .where(CHILDREN_SESSIONS.ID.eq(value.id()))
            .and(CHILDREN_SESSIONS.VERSION.eq(value.version() - 1))
            .execute();
        if (changed != 1) {
            throw ChildrenException.conflict("Record changed. Reload and try again.");
        }
    }
    private ChildrenSessionsRecord record(MinistrySession value) {
        ChildrenSessionsRecord row = db.newRecord(CHILDREN_SESSIONS);
        row.setId(value.id());
        row.setClassId(value.classId());
        row.setRoomId(value.roomId());
        row.setStartsAt(time(value.startsAt()));
        row.setEndsAt(time(value.endsAt()));
        row.setSeatLimit(value.seatLimit());
        row.setStatus(value.status().name());
        row.setVersion(value.version());
        return row;
    }
    @Override
    public void insertSessionStaff(SessionStaff value) {
        db.insertInto(CHILDREN_SESSION_STAFF)
            .set(record(value))
            .onConflict(CHILDREN_SESSION_STAFF.SESSION_ID, CHILDREN_SESSION_STAFF.WORKER_ID)
            .doUpdate()
            .set(CHILDREN_SESSION_STAFF.ROLE, value.role().name())
            .set(CHILDREN_SESSION_STAFF.PRESENT, value.present())
            .execute();
    }
    private ChildrenSessionStaffRecord record(SessionStaff value) {
        ChildrenSessionStaffRecord row = db.newRecord(CHILDREN_SESSION_STAFF);
        row.setSessionId(value.sessionId());
        row.setWorkerId(value.workerId());
        row.setRole(value.role().name());
        row.setPresent(value.present());
        return row;
    }

    public List<MinistrySession> sessions(UUID locationId, Instant from, Instant until, int limit, int offset) {
        return db.select(CHILDREN_SESSIONS.fields())
            .from(CHILDREN_SESSIONS)
            .join(CHILDREN_ROOMS)
            .on(CHILDREN_ROOMS.ID.eq(CHILDREN_SESSIONS.ROOM_ID))
            .where(locationId == null ? org.jooq.impl.DSL.noCondition() : CHILDREN_ROOMS.LOCATION_ID.eq(locationId))
            .and(CHILDREN_SESSIONS.STARTS_AT.lt(time(until)))
            .and(CHILDREN_SESSIONS.ENDS_AT.gt(time(from)))
            .orderBy(CHILDREN_SESSIONS.STARTS_AT, CHILDREN_SESSIONS.ID)
            .limit(limit)
            .offset(offset)
            .fetch(row -> ChildrenRecords.ministrySession(row.into(CHILDREN_SESSIONS)));
    }
    public List<SessionStaff> staff(UUID sessionId) {
        return db.selectFrom(CHILDREN_SESSION_STAFF)
            .where(CHILDREN_SESSION_STAFF.SESSION_ID.eq(sessionId))
            .orderBy(CHILDREN_SESSION_STAFF.WORKER_ID)
            .fetch(ChildrenRecords::sessionStaff);
    }
    public Optional<Worker> workerByAccount(UUID accountId) {
        return db.selectFrom(CHILDREN_WORKERS)
            .where(CHILDREN_WORKERS.ACCOUNT_ID.eq(accountId))
            .fetchOptional(ChildrenRecords::worker);
    }
    public List<Room> rooms(UUID locationId) {
        return db.selectFrom(CHILDREN_ROOMS)
            .where(CHILDREN_ROOMS.LOCATION_ID.eq(locationId))
            .orderBy(CHILDREN_ROOMS.NAME, CHILDREN_ROOMS.ID)
            .fetch(ChildrenRecords::room);
    }

}
