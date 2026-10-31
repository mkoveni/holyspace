package org.onechurch.childrenministry.sessions.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_ATTENDANCE;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_ATTENDANCE_SEGMENTS;

import org.jooq.Condition;
import org.jooq.DSLContext;
import org.jooq.impl.DSL;
import org.onechurch.childrenministry.sessions.application.contract.SessionCloseoutReader;
import org.onechurch.childrenministry.sessions.application.query.getsessioncloseout.SessionCloseout;
import org.onechurch.childrenministry.shared.infrastructure.persistence.ChildrenRecords;
import org.onechurch.shared.infrastructure.jooq.tables.ChildrenAttendanceSegments;
import org.springframework.stereotype.Repository;

import java.time.Instant;
import java.util.UUID;
@Repository
public class JooqSessionCloseoutStore implements SessionCloseoutReader {
    private final DSLContext db;
    public JooqSessionCloseoutStore(DSLContext db) {
        this.db = db;
    }
    @Override
    public SessionCloseout summarize(UUID sessionId, Instant now) {
        Condition present = CHILDREN_ATTENDANCE_SEGMENTS.LEFT_AT.isNull();
        ChildrenAttendanceSegments next = CHILDREN_ATTENDANCE_SEGMENTS.as("next_segment");
        Condition transferred = CHILDREN_ATTENDANCE_SEGMENTS.LEFT_AT.isNotNull()
            .and(DSL.exists(DSL.selectOne()
                .from(next)
                .where(next.ATTENDANCE_ID.eq(CHILDREN_ATTENDANCE.ID))
                .and(next.ARRIVED_AT.gt(CHILDREN_ATTENDANCE_SEGMENTS.ARRIVED_AT))));
        Condition collected = CHILDREN_ATTENDANCE_SEGMENTS.LEFT_AT.isNotNull()
            .and(CHILDREN_ATTENDANCE.CHECKED_OUT_AT.isNotNull())
            .and(transferred.not());
        Condition labelIssue = CHILDREN_ATTENDANCE.LABEL_HASH.isNull()
            .or(CHILDREN_ATTENDANCE.LABEL_EXPIRES_AT.le(ChildrenRecords.time(now)));
        return db
            .select(DSL.count(), DSL.countDistinct(CHILDREN_ATTENDANCE.CHILD_ID), DSL.count().filterWhere(present),
                DSL.count().filterWhere(collected), DSL.count().filterWhere(transferred),
                DSL.count().filterWhere(present.and(labelIssue)),
                DSL.count().filterWhere(present.and(CHILDREN_ATTENDANCE.FAILED_ATTEMPTS.gt(0))),
                DSL.count().filterWhere(collected.and(CHILDREN_ATTENDANCE.EXCEPTION_REASON.ne(""))))
            .from(CHILDREN_ATTENDANCE_SEGMENTS)
            .join(CHILDREN_ATTENDANCE)
            .on(CHILDREN_ATTENDANCE.ID.eq(CHILDREN_ATTENDANCE_SEGMENTS.ATTENDANCE_ID))
            .where(CHILDREN_ATTENDANCE_SEGMENTS.SESSION_ID.eq(sessionId))
            .fetchSingle(row -> new SessionCloseout(row.value1(), row.value2(), row.value3(), row.value4(),
                row.value5(), row.value6(), row.value7(), row.value8(), now));
    }
}
