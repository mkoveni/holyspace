package org.onechurch.childrenministry.reporting.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_CLASSES;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_PROGRAMMES;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_ROOMS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_SESSIONS;

import org.jooq.Condition;
import org.jooq.DSLContext;
import org.onechurch.childrenministry.reporting.application.contract.MinistryOverviewReader;
import org.onechurch.childrenministry.shared.infrastructure.persistence.ChildrenRecords;
import org.springframework.stereotype.Repository;

import java.time.Instant;
import java.util.List;
@Repository
public class JooqMinistryOverviewStore implements MinistryOverviewReader {
    private final DSLContext db;
    public JooqMinistryOverviewStore(DSLContext db) {
        this.db = db;
    }
    public Upcoming upcoming(Instant from, Instant until) {
        Condition where = CHILDREN_SESSIONS.STARTS_AT.gt(ChildrenRecords.time(from))
            .and(CHILDREN_SESSIONS.STARTS_AT.lt(ChildrenRecords.time(until)))
            .and(CHILDREN_SESSIONS.STATUS.notIn("CLOSED", "CANCELLED"));
        long count = db.fetchCount(CHILDREN_SESSIONS, where);
        List<Session> sessions = db
            .select(CHILDREN_SESSIONS.ID, CHILDREN_PROGRAMMES.NAME, CHILDREN_CLASSES.NAME, CHILDREN_ROOMS.NAME,
                CHILDREN_SESSIONS.STARTS_AT, CHILDREN_SESSIONS.STATUS)
            .from(CHILDREN_SESSIONS)
            .join(CHILDREN_CLASSES)
            .on(CHILDREN_CLASSES.ID.eq(CHILDREN_SESSIONS.CLASS_ID))
            .join(CHILDREN_PROGRAMMES)
            .on(CHILDREN_PROGRAMMES.ID.eq(CHILDREN_CLASSES.PROGRAMME_ID))
            .join(CHILDREN_ROOMS)
            .on(CHILDREN_ROOMS.ID.eq(CHILDREN_SESSIONS.ROOM_ID))
            .where(where)
            .orderBy(CHILDREN_SESSIONS.STARTS_AT, CHILDREN_SESSIONS.ID)
            .limit(5)
            .fetch(r -> new Session(r.value1(), r.value2(), r.value3(), r.value4(), ChildrenRecords.instant(r.value5()),
                r.value6()));
        return new Upcoming(count, sessions);
    }
}
