package org.onechurch.childrenministry.sessions.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_CLASSES;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_ROOMS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_SCHEDULING_GUARD;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_SERIES_SESSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_SESSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_SESSION_SERIES;

import org.jooq.Condition;
import org.jooq.DSLContext;
import org.jooq.Field;
import org.jooq.impl.DSL;
import org.onechurch.childrenministry.sessions.application.SessionSeriesPage;
import org.onechurch.childrenministry.sessions.application.contract.SessionSeriesReader;
import org.onechurch.childrenministry.sessions.domain.SessionSeries;
import org.onechurch.childrenministry.sessions.domain.SessionStatus;
import org.onechurch.childrenministry.sessions.domain.WeeklyRecurrence;
import org.onechurch.childrenministry.sessions.domain.repository.SessionSeriesRepository;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.infrastructure.persistence.ChildrenRecords;
import org.onechurch.shared.infrastructure.jooq.tables.records.ChildrenSessionSeriesRecord;
import org.springframework.stereotype.Repository;

import java.time.Instant;
import java.time.LocalDate;
import java.util.Arrays;
import java.util.List;
import java.util.Optional;
import java.util.Set;
import java.util.UUID;
@Repository
public class JooqSessionSeriesStore implements SessionSeriesReader, SessionSeriesRepository {
    private final DSLContext db;
    public JooqSessionSeriesStore(DSLContext db) {
        this.db = db;
    }
    @Override
    public void lockScheduling() {
        db.selectFrom(CHILDREN_SCHEDULING_GUARD).where(CHILDREN_SCHEDULING_GUARD.ID.eq(true)).forUpdate().fetchSingle();
    }
    @Override
    public Optional<SessionSeries> request(UUID actorId, UUID requestId) {
        return db.selectFrom(CHILDREN_SESSION_SERIES)
            .where(CHILDREN_SESSION_SERIES.CREATED_BY.eq(actorId))
            .and(CHILDREN_SESSION_SERIES.REQUEST_ID.eq(requestId))
            .fetchOptional(this::series);
    }
    @Override
    public Optional<SessionSeries> forUpdate(UUID id) {
        return db.selectFrom(CHILDREN_SESSION_SERIES)
            .where(CHILDREN_SESSION_SERIES.ID.eq(id))
            .forUpdate()
            .fetchOptional(this::series);
    }
    private SessionSeries series(ChildrenSessionSeriesRecord row) {
        return new SessionSeries(row.getId(), row.getName(), row.getSourceSessionId(), row.getClassId(),
            row.getRoomId(), row.getSeatLimit(), row.getTimeZone(),
            new WeeklyRecurrence(row.getFirstDate(), row.getStartsAt(), row.getEndsAt(), row.getIntervalWeeks(),
                row.getOccurrences(), Set.copyOf(Arrays.asList(row.getExcludedDates()))),
            ChildrenRecords.instant(row.getCreatedAt()), row.getCreatedBy(), row.getRequestId(),
            row.getRequestFingerprint(), row.getStopped(), row.getVersion());
    }
    @Override
    public void insert(SessionSeries value) {
        db.insertInto(CHILDREN_SESSION_SERIES)
            .set(CHILDREN_SESSION_SERIES.ID, value.id())
            .set(CHILDREN_SESSION_SERIES.NAME, value.name())
            .set(CHILDREN_SESSION_SERIES.SOURCE_SESSION_ID, value.sourceSessionId())
            .set(CHILDREN_SESSION_SERIES.CLASS_ID, value.classId())
            .set(CHILDREN_SESSION_SERIES.ROOM_ID, value.roomId())
            .set(CHILDREN_SESSION_SERIES.SEAT_LIMIT, value.seatLimit())
            .set(CHILDREN_SESSION_SERIES.TIME_ZONE, value.timeZone())
            .set(CHILDREN_SESSION_SERIES.FIRST_DATE, value.recurrence().firstDate())
            .set(CHILDREN_SESSION_SERIES.STARTS_AT, value.recurrence().startsAt())
            .set(CHILDREN_SESSION_SERIES.ENDS_AT, value.recurrence().endsAt())
            .set(CHILDREN_SESSION_SERIES.INTERVAL_WEEKS, value.recurrence().intervalWeeks())
            .set(CHILDREN_SESSION_SERIES.OCCURRENCES, value.recurrence().occurrences())
            .set(CHILDREN_SESSION_SERIES.EXCLUDED_DATES,
                value.recurrence().excludedDates().stream().sorted().toArray(LocalDate[]::new))
            .set(CHILDREN_SESSION_SERIES.CREATED_AT, ChildrenRecords.time(value.createdAt()))
            .set(CHILDREN_SESSION_SERIES.CREATED_BY, value.createdBy())
            .set(CHILDREN_SESSION_SERIES.REQUEST_ID, value.requestId())
            .set(CHILDREN_SESSION_SERIES.REQUEST_FINGERPRINT, value.requestFingerprint())
            .execute();
    }
    @Override
    public void update(SessionSeries value) {
        int changed = db.update(CHILDREN_SESSION_SERIES)
            .set(CHILDREN_SESSION_SERIES.STOPPED, value.stopped())
            .set(CHILDREN_SESSION_SERIES.VERSION, value.version())
            .where(CHILDREN_SESSION_SERIES.ID.eq(value.id()))
            .and(CHILDREN_SESSION_SERIES.VERSION.eq(value.version() - 1))
            .execute();
        if (changed != 1) {
            throw ChildrenException.conflict("The series changed. Reload before trying again.");
        }
    }
    @Override
    public void link(UUID seriesId, UUID sessionId, LocalDate date) {
        db.insertInto(CHILDREN_SERIES_SESSIONS)
            .set(CHILDREN_SERIES_SESSIONS.SERIES_ID, seriesId)
            .set(CHILDREN_SERIES_SESSIONS.SESSION_ID, sessionId)
            .set(CHILDREN_SERIES_SESSIONS.SCHEDULED_DATE, date)
            .execute();
    }
    @Override
    public List<UUID> sessionIds(UUID seriesId) {
        return db.select(CHILDREN_SERIES_SESSIONS.SESSION_ID)
            .from(CHILDREN_SERIES_SESSIONS)
            .where(CHILDREN_SERIES_SESSIONS.SERIES_ID.eq(seriesId))
            .fetch(CHILDREN_SERIES_SESSIONS.SESSION_ID);
    }
    @Override
    public boolean conflicts(UUID classId, UUID roomId, Instant from, Instant until) {
        return db.fetchExists(db.selectOne()
            .from(CHILDREN_SESSIONS)
            .where(CHILDREN_SESSIONS.STATUS.ne("CANCELLED"))
            .and(CHILDREN_SESSIONS.CLASS_ID.eq(classId).or(CHILDREN_SESSIONS.ROOM_ID.eq(roomId)))
            .and(CHILDREN_SESSIONS.STARTS_AT.lt(ChildrenRecords.time(until)))
            .and(CHILDREN_SESSIONS.ENDS_AT.gt(ChildrenRecords.time(from))));
    }
    @Override
    public SessionSeriesPage list(int limit, int offset) {
        return new SessionSeriesPage(summaries(DSL.noCondition(), limit, offset),
            db.fetchCount(CHILDREN_SESSION_SERIES));
    }
    @Override
    public Optional<SessionSeriesPage.Summary> summary(UUID id) {
        return summaries(CHILDREN_SESSION_SERIES.ID.eq(id), 1, 0).stream().findFirst();
    }
    private Field<Integer> count(String status) {
        return db.selectCount()
            .from(CHILDREN_SERIES_SESSIONS)
            .join(CHILDREN_SESSIONS)
            .on(CHILDREN_SESSIONS.ID.eq(CHILDREN_SERIES_SESSIONS.SESSION_ID))
            .where(CHILDREN_SERIES_SESSIONS.SERIES_ID.eq(CHILDREN_SESSION_SERIES.ID))
            .and(status == null ? DSL.noCondition() : CHILDREN_SESSIONS.STATUS.eq(status))
            .asField();
    }
    private List<SessionSeriesPage.Summary> summaries(Condition where, int limit, int offset) {
        return db
            .select(CHILDREN_SESSION_SERIES.ID, CHILDREN_SESSION_SERIES.NAME, CHILDREN_SESSION_SERIES.SOURCE_SESSION_ID,
                CHILDREN_CLASSES.NAME, CHILDREN_ROOMS.NAME, CHILDREN_SESSION_SERIES.TIME_ZONE,
                CHILDREN_SESSION_SERIES.FIRST_DATE, CHILDREN_SESSION_SERIES.INTERVAL_WEEKS,
                CHILDREN_SESSION_SERIES.OCCURRENCES, count(null), count("PLANNED"), count("CANCELLED"),
                CHILDREN_SESSION_SERIES.STOPPED, CHILDREN_SESSION_SERIES.VERSION)
            .from(CHILDREN_SESSION_SERIES)
            .join(CHILDREN_CLASSES)
            .on(CHILDREN_CLASSES.ID.eq(CHILDREN_SESSION_SERIES.CLASS_ID))
            .join(CHILDREN_ROOMS)
            .on(CHILDREN_ROOMS.ID.eq(CHILDREN_SESSION_SERIES.ROOM_ID))
            .where(where)
            .orderBy(CHILDREN_SESSION_SERIES.CREATED_AT.desc(), CHILDREN_SESSION_SERIES.ID)
            .limit(limit)
            .offset(offset)
            .fetch(row -> new SessionSeriesPage.Summary(row.value1(), row.value2(), row.value3(), row.value4(),
                row.value5(), row.value6(), row.value7(), row.value8(), row.value9(), row.value10(), row.value11(),
                row.value12(), row.value13(), row.value14()));
    }
    @Override
    public List<SessionSeriesPage.ScheduledSession> sessions(UUID id) {
        return db
            .select(CHILDREN_SESSIONS.ID, CHILDREN_SERIES_SESSIONS.SCHEDULED_DATE, CHILDREN_SESSIONS.STARTS_AT,
                CHILDREN_SESSIONS.ENDS_AT, CHILDREN_SESSIONS.STATUS)
            .from(CHILDREN_SERIES_SESSIONS)
            .join(CHILDREN_SESSIONS)
            .on(CHILDREN_SESSIONS.ID.eq(CHILDREN_SERIES_SESSIONS.SESSION_ID))
            .where(CHILDREN_SERIES_SESSIONS.SERIES_ID.eq(id))
            .orderBy(CHILDREN_SERIES_SESSIONS.SCHEDULED_DATE)
            .fetch(row -> new SessionSeriesPage.ScheduledSession(row.value1(), row.value2(),
                ChildrenRecords.instant(row.value3()), ChildrenRecords.instant(row.value4()),
                SessionStatus.valueOf(row.value5())));
    }
}
