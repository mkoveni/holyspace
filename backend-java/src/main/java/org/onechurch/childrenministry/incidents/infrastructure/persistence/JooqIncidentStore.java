package org.onechurch.childrenministry.incidents.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_ATTENDANCE;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_ATTENDANCE_SEGMENTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_INCIDENTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_INCIDENT_DETAILS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_INCIDENT_EVENTS;

import org.jooq.Condition;
import org.jooq.DSLContext;
import org.jooq.impl.DSL;
import org.onechurch.childrenministry.incidents.application.contract.IncidentReader;
import org.onechurch.childrenministry.incidents.domain.Incident;
import org.onechurch.childrenministry.incidents.domain.IncidentDetails;
import org.onechurch.childrenministry.incidents.domain.IncidentEvent;
import org.onechurch.childrenministry.incidents.domain.repository.IncidentRepository;
import org.onechurch.childrenministry.shared.infrastructure.persistence.ChildrenRecords;
import org.onechurch.shared.infrastructure.jooq.tables.records.ChildrenIncidentsRecord;
import org.springframework.stereotype.Repository;

import java.time.Instant;
import java.time.LocalDate;
import java.util.Collection;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
@Repository
public class JooqIncidentStore implements IncidentReader, IncidentRepository {
    private final DSLContext db;
    public JooqIncidentStore(DSLContext db) {
        this.db = db;
    }
    private Incident map(ChildrenIncidentsRecord r) {
        return new Incident(r.getId(), r.getChildId(), r.getSessionId(), r.getSegmentId(), r.getCategory(),
            r.getSeverity(), ChildrenRecords.instant(r.getOccurredAt()), r.getReportedBy(),
            ChildrenRecords.instant(r.getReportedAt()), r.getStatus(), r.getOwnerId(), r.getReviewOn(),
            r.getRetainUntil(), r.getVersion());
    }
    public Optional<Incident> lock(UUID id) {
        return db.selectFrom(CHILDREN_INCIDENTS)
            .where(CHILDREN_INCIDENTS.ID.eq(id))
            .forUpdate()
            .fetchOptional(this::map);
    }
    public Optional<Incident> find(UUID id) {
        return db.selectFrom(CHILDREN_INCIDENTS).where(CHILDREN_INCIDENTS.ID.eq(id)).fetchOptional(this::map);
    }
    public Optional<Receipt> receipt(UUID actorId, UUID requestId) {
        return db.select(CHILDREN_INCIDENTS.ID, CHILDREN_INCIDENTS.REQUEST_HASH)
            .from(CHILDREN_INCIDENTS)
            .where(CHILDREN_INCIDENTS.REPORTED_BY.eq(actorId))
            .and(CHILDREN_INCIDENTS.REQUEST_ID.eq(requestId))
            .fetchOptional(r -> new Receipt(r.value1(), r.value2()));
    }
    public void insert(Incident i, IncidentDetails d, UUID requestId, String hash) {
        db.insertInto(CHILDREN_INCIDENTS)
            .set(CHILDREN_INCIDENTS.ID, i.id())
            .set(CHILDREN_INCIDENTS.CHILD_ID, i.childId())
            .set(CHILDREN_INCIDENTS.SESSION_ID, i.sessionId())
            .set(CHILDREN_INCIDENTS.SEGMENT_ID, i.segmentId())
            .set(CHILDREN_INCIDENTS.CATEGORY, i.category())
            .set(CHILDREN_INCIDENTS.SEVERITY, i.severity())
            .set(CHILDREN_INCIDENTS.OCCURRED_AT, ChildrenRecords.time(i.occurredAt()))
            .set(CHILDREN_INCIDENTS.REPORTED_BY, i.reportedBy())
            .set(CHILDREN_INCIDENTS.REPORTED_AT, ChildrenRecords.time(i.reportedAt()))
            .set(CHILDREN_INCIDENTS.REQUEST_ID, requestId)
            .set(CHILDREN_INCIDENTS.REQUEST_HASH, hash)
            .set(CHILDREN_INCIDENTS.STATUS, i.status())
            .set(CHILDREN_INCIDENTS.REVIEW_ON, i.reviewOn())
            .set(CHILDREN_INCIDENTS.RETAIN_UNTIL, i.retainUntil())
            .execute();
        db.insertInto(CHILDREN_INCIDENT_DETAILS)
            .set(CHILDREN_INCIDENT_DETAILS.INCIDENT_ID, i.id())
            .set(CHILDREN_INCIDENT_DETAILS.DESCRIPTION, d.description())
            .set(CHILDREN_INCIDENT_DETAILS.IMMEDIATE_ACTION, d.immediateAction())
            .execute();
    }
    public void update(Incident i) {
        db.update(CHILDREN_INCIDENTS)
            .set(CHILDREN_INCIDENTS.STATUS, i.status())
            .set(CHILDREN_INCIDENTS.OWNER_ID, i.ownerId())
            .set(CHILDREN_INCIDENTS.REVIEW_ON, i.reviewOn())
            .set(CHILDREN_INCIDENTS.VERSION, i.version())
            .where(CHILDREN_INCIDENTS.ID.eq(i.id()))
            .execute();
    }
    public void append(IncidentEvent e) {
        db.insertInto(CHILDREN_INCIDENT_EVENTS)
            .set(CHILDREN_INCIDENT_EVENTS.ID, e.id())
            .set(CHILDREN_INCIDENT_EVENTS.INCIDENT_ID, e.incidentId())
            .set(CHILDREN_INCIDENT_EVENTS.REVISION, e.revision())
            .set(CHILDREN_INCIDENT_EVENTS.TYPE, e.type())
            .set(CHILDREN_INCIDENT_EVENTS.ACTOR_ID, e.actorId())
            .set(CHILDREN_INCIDENT_EVENTS.OCCURRED_AT, ChildrenRecords.time(e.occurredAt()))
            .set(CHILDREN_INCIDENT_EVENTS.NOTE, e.note())
            .set(CHILDREN_INCIDENT_EVENTS.GUARDIAN_ID, e.guardianId())
            .execute();
    }
    public Optional<IncidentDetails> details(UUID id) {
        return db.selectFrom(CHILDREN_INCIDENT_DETAILS)
            .where(CHILDREN_INCIDENT_DETAILS.INCIDENT_ID.eq(id))
            .fetchOptional(r -> new IncidentDetails(r.getDescription(), r.getImmediateAction()));
    }
    public List<IncidentEvent> events(UUID id, int limit, int offset) {
        return db.selectFrom(CHILDREN_INCIDENT_EVENTS)
            .where(CHILDREN_INCIDENT_EVENTS.INCIDENT_ID.eq(id))
            .orderBy(CHILDREN_INCIDENT_EVENTS.REVISION.desc())
            .limit(limit)
            .offset(offset)
            .fetch(r -> new IncidentEvent(r.getId(), r.getIncidentId(), r.getRevision(), r.getType(), r.getActorId(),
                ChildrenRecords.instant(r.getOccurredAt()), r.getNote(), r.getGuardianId()));
    }
    public long eventCount(UUID id) {
        return db.fetchCount(CHILDREN_INCIDENT_EVENTS, CHILDREN_INCIDENT_EVENTS.INCIDENT_ID.eq(id));
    }
    public boolean attended(UUID childId, UUID sessionId, UUID segmentId, Instant at) {
        return db.fetchExists(db.selectOne()
            .from(CHILDREN_ATTENDANCE_SEGMENTS)
            .join(CHILDREN_ATTENDANCE)
            .on(CHILDREN_ATTENDANCE.ID.eq(CHILDREN_ATTENDANCE_SEGMENTS.ATTENDANCE_ID))
            .where(CHILDREN_ATTENDANCE.CHILD_ID.eq(childId))
            .and(CHILDREN_ATTENDANCE_SEGMENTS.ID.eq(segmentId))
            .and(CHILDREN_ATTENDANCE_SEGMENTS.SESSION_ID.eq(sessionId))
            .and(CHILDREN_ATTENDANCE_SEGMENTS.ARRIVED_AT.le(ChildrenRecords.time(at)))
            .and(CHILDREN_ATTENDANCE_SEGMENTS.LEFT_AT.isNull()
                .or(CHILDREN_ATTENDANCE_SEGMENTS.LEFT_AT.gt(ChildrenRecords.time(at)))));
    }
    public Page list(UUID sessionId, UUID childId, String status, boolean overdueOnly, LocalDate on, int limit,
        int offset) {
        Condition where = CHILDREN_INCIDENTS.RETAIN_UNTIL.ge(on);
        if (sessionId != null)
            where = where.and(CHILDREN_INCIDENTS.SESSION_ID.eq(sessionId));
        if (childId != null)
            where = where.and(CHILDREN_INCIDENTS.CHILD_ID.eq(childId));
        if (!status.equals("ALL"))
            where = where.and(status.equals("ACTIVE") ? CHILDREN_INCIDENTS.STATUS.ne("CLOSED")
                : CHILDREN_INCIDENTS.STATUS.eq(status));
        Condition active = CHILDREN_INCIDENTS.STATUS.ne("CLOSED");
        long overdue = db.fetchCount(CHILDREN_INCIDENTS, where.and(active).and(CHILDREN_INCIDENTS.REVIEW_ON.le(on)));
        long unassigned =
            db.fetchCount(CHILDREN_INCIDENTS, where.and(active).and(CHILDREN_INCIDENTS.OWNER_ID.isNull()));
        if (overdueOnly)
            where = where.and(active).and(CHILDREN_INCIDENTS.REVIEW_ON.le(on));
        return new Page(db.selectFrom(CHILDREN_INCIDENTS)
            .where(where)
            .orderBy(CHILDREN_INCIDENTS.REVIEW_ON, CHILDREN_INCIDENTS.ID)
            .limit(limit)
            .offset(offset)
            .fetch(this::map), db.fetchCount(CHILDREN_INCIDENTS, where), overdue, unassigned);
    }
    public Counts counts(UUID sessionId, LocalDate on) {
        Condition active = CHILDREN_INCIDENTS.SESSION_ID.eq(sessionId)
            .and(CHILDREN_INCIDENTS.STATUS.ne("CLOSED"))
            .and(CHILDREN_INCIDENTS.RETAIN_UNTIL.ge(on));
        return new Counts(db.fetchCount(CHILDREN_INCIDENTS, active),
            db.fetchCount(CHILDREN_INCIDENTS, active.and(CHILDREN_INCIDENTS.REVIEW_ON.le(on))),
            db.fetchCount(CHILDREN_INCIDENTS, active.and(CHILDREN_INCIDENTS.OWNER_ID.isNull())));
    }
    public int purgeExpired(LocalDate on, int limit) {
        List<UUID> ids = db.select(CHILDREN_INCIDENTS.ID)
            .from(CHILDREN_INCIDENTS)
            .where(CHILDREN_INCIDENTS.RETAIN_UNTIL.lt(on))
            .and(DSL.exists(db.selectOne()
                .from(CHILDREN_INCIDENT_DETAILS)
                .where(CHILDREN_INCIDENT_DETAILS.INCIDENT_ID.eq(CHILDREN_INCIDENTS.ID))))
            .orderBy(CHILDREN_INCIDENTS.ID)
            .limit(limit)
            .forUpdate()
            .fetch(CHILDREN_INCIDENTS.ID);
        db.update(CHILDREN_INCIDENT_EVENTS)
            .set(CHILDREN_INCIDENT_EVENTS.NOTE, "")
            .set(CHILDREN_INCIDENT_EVENTS.GUARDIAN_ID, (UUID) null)
            .where(CHILDREN_INCIDENT_EVENTS.INCIDENT_ID.in(ids))
            .execute();
        return db.deleteFrom(CHILDREN_INCIDENT_DETAILS).where(CHILDREN_INCIDENT_DETAILS.INCIDENT_ID.in(ids)).execute();
    }
    public Visits visits(UUID sessionId, Collection<UUID> matchingIds, int limit, int offset) {
        Condition where = CHILDREN_ATTENDANCE_SEGMENTS.SESSION_ID.eq(sessionId);
        if (matchingIds != null)
            where = where.and(CHILDREN_ATTENDANCE.CHILD_ID.in(matchingIds));
        long total = db.selectCount()
            .from(CHILDREN_ATTENDANCE_SEGMENTS)
            .join(CHILDREN_ATTENDANCE)
            .on(CHILDREN_ATTENDANCE.ID.eq(CHILDREN_ATTENDANCE_SEGMENTS.ATTENDANCE_ID))
            .where(where)
            .fetchSingle(0, int.class);
        List<Visit> visits = db
            .select(CHILDREN_ATTENDANCE_SEGMENTS.ID, CHILDREN_ATTENDANCE.CHILD_ID,
                CHILDREN_ATTENDANCE_SEGMENTS.ARRIVED_AT, CHILDREN_ATTENDANCE_SEGMENTS.LEFT_AT)
            .from(CHILDREN_ATTENDANCE_SEGMENTS)
            .join(CHILDREN_ATTENDANCE)
            .on(CHILDREN_ATTENDANCE.ID.eq(CHILDREN_ATTENDANCE_SEGMENTS.ATTENDANCE_ID))
            .where(where)
            .orderBy(CHILDREN_ATTENDANCE_SEGMENTS.ARRIVED_AT.desc(), CHILDREN_ATTENDANCE_SEGMENTS.ID)
            .limit(limit)
            .offset(offset)
            .fetch(r -> new Visit(r.value1(), r.value2(), ChildrenRecords.instant(r.value3()),
                ChildrenRecords.instant(r.value4())));
        return new Visits(visits, total);
    }

}
