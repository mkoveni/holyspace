package org.onechurch.volunteering.infrastructure.persistence;

import static org.onechurch.shared.infrastructure.jooq.Tables.SERVING_ASSIGNMENTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.SERVING_ASSIGNMENT_EVENTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.SERVING_INVITATIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.SERVING_SHIFTS;

import org.jooq.Condition;
import org.jooq.DSLContext;
import org.jooq.impl.DSL;
import org.onechurch.volunteering.application.contract.MemberDutyReader;
import org.onechurch.volunteering.application.contract.RosterReader;
import org.onechurch.volunteering.application.contract.ServingActivityLocks;
import org.onechurch.volunteering.domain.Assignment;
import org.onechurch.volunteering.domain.Invitation;
import org.onechurch.volunteering.domain.Shift;
import org.onechurch.volunteering.domain.VolunteerException;
import org.onechurch.volunteering.domain.repository.RosterRepository;
import org.springframework.stereotype.Repository;

import java.time.Instant;
import java.util.List;
import java.util.Optional;
import java.util.UUID;

@Repository
public class JooqRosterStore implements RosterReader, RosterRepository, MemberDutyReader {
    private final DSLContext db;
    public JooqRosterStore(DSLContext db) {
        this.db = db;
    }
    public List<ServingActivityLocks.Activity> volunteerActivities(List<UUID> volunteers) {
        return activities(db.selectDistinct(SERVING_SHIFTS.SOURCE_KIND, SERVING_SHIFTS.SOURCE_ID)
            .from(SERVING_SHIFTS)
            .join(SERVING_ASSIGNMENTS)
            .on(SERVING_ASSIGNMENTS.SHIFT_ID.eq(SERVING_SHIFTS.ID))
            .where(SERVING_ASSIGNMENTS.VOLUNTEER_ID.in(volunteers))
            .and(SERVING_SHIFTS.SOURCE_ID.isNotNull())
            .and(SERVING_ASSIGNMENTS.STATUS.ne("CANCELLED"))
            .limit(1001)
            .fetch(r -> new ServingActivityLocks.Activity(r.value1(), r.value2())));
    }
    public List<ServingActivityLocks.Activity> teamActivities(UUID team) {
        return activities(db.selectDistinct(SERVING_SHIFTS.SOURCE_KIND, SERVING_SHIFTS.SOURCE_ID)
            .from(SERVING_SHIFTS)
            .where(SERVING_SHIFTS.TEAM_ID.eq(team))
            .and(SERVING_SHIFTS.SOURCE_ID.isNotNull())
            .limit(1001)
            .fetch(r -> new ServingActivityLocks.Activity(r.value1(), r.value2())));
    }
    private List<ServingActivityLocks.Activity> activities(List<ServingActivityLocks.Activity> activities) {
        if (activities.size() > 1000) {
            throw VolunteerException
                .conflict("Too many linked activities. Archive completed planning before changing eligibility.");
        }
        return activities;
    }
    public List<Shift> sourceShifts(String kind, UUID sourceId, int limit) {
        return db.selectFrom(SERVING_SHIFTS)
            .where(SERVING_SHIFTS.SOURCE_KIND.eq(kind).and(SERVING_SHIFTS.SOURCE_ID.eq(sourceId)))
            .orderBy(SERVING_SHIFTS.STARTS_AT, SERVING_SHIFTS.ID)
            .limit(limit)
            .fetch(ServingRecords::shift);
    }
    public Optional<Shift> shift(UUID id) {
        return db.selectFrom(SERVING_SHIFTS).where(SERVING_SHIFTS.ID.eq(id)).fetchOptional(ServingRecords::shift);
    }
    public Optional<Shift> shiftRequest(UUID id) {
        return db.selectFrom(SERVING_SHIFTS)
            .where(SERVING_SHIFTS.REQUEST_ID.eq(id))
            .fetchOptional(ServingRecords::shift);
    }
    public Optional<Shift> lockShift(UUID id) {
        return db.selectFrom(SERVING_SHIFTS)
            .where(SERVING_SHIFTS.ID.eq(id))
            .forUpdate()
            .fetchOptional(ServingRecords::shift);
    }
    private Condition range(Instant from, Instant until, UUID team) {
        return SERVING_SHIFTS.STARTS_AT.lt(ServingRecords.time(until))
            .and(SERVING_SHIFTS.ENDS_AT.gt(ServingRecords.time(from)))
            .and(team == null ? DSL.noCondition() : SERVING_SHIFTS.TEAM_ID.eq(team));
    }
    public List<Shift> shifts(Instant from, Instant until, UUID team, int limit, int offset) {
        return db.selectFrom(SERVING_SHIFTS)
            .where(range(from, until, team))
            .orderBy(SERVING_SHIFTS.STARTS_AT, SERVING_SHIFTS.ID)
            .limit(limit)
            .offset(offset)
            .fetch(ServingRecords::shift);
    }
    public long shiftCount(Instant from, Instant until, UUID team) {
        return db.fetchCount(SERVING_SHIFTS, range(from, until, team));
    }
    public Optional<Assignment> assignment(UUID id) {
        return db.selectFrom(SERVING_ASSIGNMENTS)
            .where(SERVING_ASSIGNMENTS.ID.eq(id))
            .fetchOptional(ServingRecords::assignment);
    }
    public Optional<Assignment> assignmentRequest(UUID id) {
        return db.selectFrom(SERVING_ASSIGNMENTS)
            .where(SERVING_ASSIGNMENTS.REQUEST_ID.eq(id))
            .fetchOptional(ServingRecords::assignment);
    }
    public Optional<Assignment> lockAssignment(UUID id) {
        return db.selectFrom(SERVING_ASSIGNMENTS)
            .where(SERVING_ASSIGNMENTS.ID.eq(id))
            .forUpdate()
            .fetchOptional(ServingRecords::assignment);
    }
    public List<Assignment> assignments(UUID shift) {
        return db.selectFrom(SERVING_ASSIGNMENTS)
            .where(SERVING_ASSIGNMENTS.SHIFT_ID.eq(shift))
            .orderBy(SERVING_ASSIGNMENTS.STATUS.eq("CANCELLED").asc(), SERVING_ASSIGNMENTS.CREATED_AT.desc(),
                SERVING_ASSIGNMENTS.ID)
            .limit(1000)
            .fetch(ServingRecords::assignment);
    }
    public List<Assignment> volunteerAssignments(UUID volunteer, Instant from, Instant until) {
        return db.select(SERVING_ASSIGNMENTS.fields())
            .from(SERVING_ASSIGNMENTS)
            .join(SERVING_SHIFTS)
            .on(SERVING_SHIFTS.ID.eq(SERVING_ASSIGNMENTS.SHIFT_ID))
            .where(SERVING_ASSIGNMENTS.VOLUNTEER_ID.eq(volunteer))
            .and(range(from, until, null))
            .and(SERVING_ASSIGNMENTS.STATUS.ne("CANCELLED"))
            .and(SERVING_SHIFTS.STATUS.ne("CANCELLED"))
            .orderBy(SERVING_SHIFTS.STARTS_AT, SERVING_ASSIGNMENTS.ID)
            .limit(1001)
            .fetch(row -> ServingRecords.assignment(row.into(SERVING_ASSIGNMENTS)));
    }
    @Override
    public List<Assignment> duties(UUID volunteer, Instant from, Instant until, int limit, int offset) {
        return db.select(SERVING_ASSIGNMENTS.fields())
            .from(SERVING_ASSIGNMENTS)
            .join(SERVING_SHIFTS)
            .on(SERVING_SHIFTS.ID.eq(SERVING_ASSIGNMENTS.SHIFT_ID))
            .where(SERVING_ASSIGNMENTS.VOLUNTEER_ID.eq(volunteer))
            .and(range(from, until, null))
            .orderBy(SERVING_SHIFTS.STARTS_AT, SERVING_ASSIGNMENTS.ID)
            .limit(limit)
            .offset(offset)
            .fetch(row -> ServingRecords.assignment(row.into(SERVING_ASSIGNMENTS)));
    }
    public Optional<Invitation> invitation(UUID id) {
        return db.selectFrom(SERVING_INVITATIONS)
            .where(SERVING_INVITATIONS.ID.eq(id))
            .fetchOptional(ServingRecords::invitation);
    }
    public Optional<Invitation> lockInvitation(UUID id) {
        return db.selectFrom(SERVING_INVITATIONS)
            .where(SERVING_INVITATIONS.ID.eq(id))
            .forUpdate()
            .fetchOptional(ServingRecords::invitation);
    }
    public List<Invitation> invitations(UUID assignment) {
        return db.selectFrom(SERVING_INVITATIONS)
            .where(SERVING_INVITATIONS.ASSIGNMENT_ID.eq(assignment))
            .orderBy(SERVING_INVITATIONS.STATUS.eq("PENDING").desc(), SERVING_INVITATIONS.EXPIRES_AT.desc(),
                SERVING_INVITATIONS.ID)
            .limit(100)
            .fetch(ServingRecords::invitation);
    }
    public List<Invitation> expirable(Instant at, int limit) {
        return db.selectFrom(SERVING_INVITATIONS)
            .where(SERVING_INVITATIONS.STATUS.eq("PENDING"))
            .and(SERVING_INVITATIONS.EXPIRES_AT.le(ServingRecords.time(at)))
            .orderBy(SERVING_INVITATIONS.EXPIRES_AT, SERVING_INVITATIONS.ID)
            .limit(limit)
            .fetch(ServingRecords::invitation);
    }
    public List<Invitation> myInvitations(UUID volunteer, int limit, int offset) {
        return db.select(SERVING_INVITATIONS.fields())
            .from(SERVING_INVITATIONS)
            .join(SERVING_ASSIGNMENTS)
            .on(SERVING_ASSIGNMENTS.ID.eq(SERVING_INVITATIONS.ASSIGNMENT_ID))
            .where(SERVING_ASSIGNMENTS.VOLUNTEER_ID.eq(volunteer))
            .orderBy(SERVING_INVITATIONS.STATUS.eq("PENDING").desc(), SERVING_INVITATIONS.EXPIRES_AT.desc(),
                SERVING_INVITATIONS.ID)
            .limit(limit)
            .offset(offset)
            .fetch(row -> ServingRecords.invitation(row.into(SERVING_INVITATIONS)));
    }
    public List<Event> events(UUID assignment, int limit, int offset) {
        return db.selectFrom(SERVING_ASSIGNMENT_EVENTS)
            .where(SERVING_ASSIGNMENT_EVENTS.ASSIGNMENT_ID.eq(assignment))
            .orderBy(SERVING_ASSIGNMENT_EVENTS.OCCURRED_AT.desc(), SERVING_ASSIGNMENT_EVENTS.ID)
            .limit(limit)
            .offset(offset)
            .fetch(row -> new Event(row.getId(), row.getAction(), row.getReason(),
                ServingRecords.instant(row.getOccurredAt())));
    }
    public void event(UUID assignment, UUID actor, String action, String reason, Instant at) {
        db.insertInto(SERVING_ASSIGNMENT_EVENTS)
            .set(SERVING_ASSIGNMENT_EVENTS.ID, UUID.randomUUID())
            .set(SERVING_ASSIGNMENT_EVENTS.ASSIGNMENT_ID, assignment)
            .set(SERVING_ASSIGNMENT_EVENTS.ACTOR_ID, actor)
            .set(SERVING_ASSIGNMENT_EVENTS.ACTION, action)
            .set(SERVING_ASSIGNMENT_EVENTS.REASON, reason)
            .set(SERVING_ASSIGNMENT_EVENTS.OCCURRED_AT, ServingRecords.time(at))
            .execute();
    }
    public void insertShift(Shift v) {
        db.insertInto(SERVING_SHIFTS)
            .set(SERVING_SHIFTS.ID, v.id())
            .set(SERVING_SHIFTS.REQUEST_ID, v.requestId())
            .set(SERVING_SHIFTS.REQUEST_HASH, v.requestHash())
            .set(SERVING_SHIFTS.TEAM_ID, v.teamId())
            .set(SERVING_SHIFTS.ROLE_ID, v.roleId())
            .set(SERVING_SHIFTS.SOURCE_KIND, v.sourceKind())
            .set(SERVING_SHIFTS.SOURCE_ID, v.sourceId())
            .set(SERVING_SHIFTS.STARTS_AT, ServingRecords.time(v.startsAt()))
            .set(SERVING_SHIFTS.ENDS_AT, ServingRecords.time(v.endsAt()))
            .set(SERVING_SHIFTS.POSITIONS, v.positions())
            .set(SERVING_SHIFTS.STATUS, v.status())
            .set(SERVING_SHIFTS.VERSION, v.version())
            .set(SERVING_SHIFTS.SOURCE_REVISION, v.sourceRevision())
            .set(SERVING_SHIFTS.SCHEDULE_REVISION, v.scheduleRevision())
            .execute();
    }
    public void saveShift(Shift v) {
        db.update(SERVING_SHIFTS)
            .set(SERVING_SHIFTS.REQUEST_ID, v.requestId())
            .set(SERVING_SHIFTS.REQUEST_HASH, v.requestHash())
            .set(SERVING_SHIFTS.TEAM_ID, v.teamId())
            .set(SERVING_SHIFTS.ROLE_ID, v.roleId())
            .set(SERVING_SHIFTS.SOURCE_KIND, v.sourceKind())
            .set(SERVING_SHIFTS.SOURCE_ID, v.sourceId())
            .set(SERVING_SHIFTS.STARTS_AT, ServingRecords.time(v.startsAt()))
            .set(SERVING_SHIFTS.ENDS_AT, ServingRecords.time(v.endsAt()))
            .set(SERVING_SHIFTS.POSITIONS, v.positions())
            .set(SERVING_SHIFTS.STATUS, v.status())
            .set(SERVING_SHIFTS.VERSION, v.version())
            .set(SERVING_SHIFTS.SOURCE_REVISION, v.sourceRevision())
            .set(SERVING_SHIFTS.SCHEDULE_REVISION, v.scheduleRevision())
            .where(SERVING_SHIFTS.ID.eq(v.id()))
            .execute();
    }
    public void insertAssignment(Assignment v) {
        db.insertInto(SERVING_ASSIGNMENTS)
            .set(SERVING_ASSIGNMENTS.ID, v.id())
            .set(SERVING_ASSIGNMENTS.REQUEST_ID, v.requestId())
            .set(SERVING_ASSIGNMENTS.REQUEST_HASH, v.requestHash())
            .set(SERVING_ASSIGNMENTS.SHIFT_ID, v.shiftId())
            .set(SERVING_ASSIGNMENTS.VOLUNTEER_ID, v.volunteerId())
            .set(SERVING_ASSIGNMENTS.STATUS, v.status())
            .set(SERVING_ASSIGNMENTS.VERSION, v.version())
            .set(SERVING_ASSIGNMENTS.CREATED_BY, v.createdBy())
            .set(SERVING_ASSIGNMENTS.CREATED_AT, ServingRecords.time(v.createdAt()))
            .execute();
    }
    public void saveAssignment(Assignment v) {
        db.update(SERVING_ASSIGNMENTS)
            .set(SERVING_ASSIGNMENTS.REQUEST_ID, v.requestId())
            .set(SERVING_ASSIGNMENTS.REQUEST_HASH, v.requestHash())
            .set(SERVING_ASSIGNMENTS.SHIFT_ID, v.shiftId())
            .set(SERVING_ASSIGNMENTS.VOLUNTEER_ID, v.volunteerId())
            .set(SERVING_ASSIGNMENTS.STATUS, v.status())
            .set(SERVING_ASSIGNMENTS.VERSION, v.version())
            .set(SERVING_ASSIGNMENTS.CREATED_BY, v.createdBy())
            .set(SERVING_ASSIGNMENTS.CREATED_AT, ServingRecords.time(v.createdAt()))
            .where(SERVING_ASSIGNMENTS.ID.eq(v.id()))
            .execute();
    }
    public void insertInvitation(Invitation v) {
        db.insertInto(SERVING_INVITATIONS)
            .set(SERVING_INVITATIONS.ID, v.id())
            .set(SERVING_INVITATIONS.ASSIGNMENT_ID, v.assignmentId())
            .set(SERVING_INVITATIONS.ASSIGNMENT_VERSION, v.assignmentVersion())
            .set(SERVING_INVITATIONS.SHIFT_VERSION, v.shiftVersion())
            .set(SERVING_INVITATIONS.EXPIRES_AT, ServingRecords.time(v.expiresAt()))
            .set(SERVING_INVITATIONS.STATUS, v.status())
            .set(SERVING_INVITATIONS.VERSION, v.version())
            .set(SERVING_INVITATIONS.RESPONDED_AT, ServingRecords.time(v.respondedAt()))
            .execute();
    }
    public void saveInvitation(Invitation v) {
        db.update(SERVING_INVITATIONS)
            .set(SERVING_INVITATIONS.ASSIGNMENT_ID, v.assignmentId())
            .set(SERVING_INVITATIONS.ASSIGNMENT_VERSION, v.assignmentVersion())
            .set(SERVING_INVITATIONS.SHIFT_VERSION, v.shiftVersion())
            .set(SERVING_INVITATIONS.EXPIRES_AT, ServingRecords.time(v.expiresAt()))
            .set(SERVING_INVITATIONS.STATUS, v.status())
            .set(SERVING_INVITATIONS.VERSION, v.version())
            .set(SERVING_INVITATIONS.RESPONDED_AT, ServingRecords.time(v.respondedAt()))
            .where(SERVING_INVITATIONS.ID.eq(v.id()))
            .execute();
    }
}
