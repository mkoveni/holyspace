package org.onechurch.childrenministry.sessions.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_ATTENDANCE;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_CLASS_ASSIGNMENTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_ENROLMENTS;

import org.jooq.Condition;
import org.jooq.DSLContext;
import org.jooq.impl.DSL;
import org.onechurch.childrenministry.sessions.application.contract.SessionDayReader;
import org.onechurch.childrenministry.sessions.domain.Attendance;
import org.onechurch.childrenministry.shared.infrastructure.persistence.ChildrenRecords;
import org.springframework.stereotype.Repository;

import java.time.LocalDate;
import java.util.Collection;
import java.util.List;
import java.util.Map;
import java.util.UUID;
@Repository
public class JooqSessionDayStore implements SessionDayReader {
    private final DSLContext db;
    public JooqSessionDayStore(DSLContext db) {
        this.db = db;
    }
    public ExpectedPage expected(UUID classId, LocalDate on, Collection<UUID> matchingIds, int limit, int offset) {
        Condition condition = CHILDREN_CLASS_ASSIGNMENTS.CLASS_ID.eq(classId)
            .and(CHILDREN_CLASS_ASSIGNMENTS.ENDED_AT.isNull())
            .and(CHILDREN_CLASS_ASSIGNMENTS.VALID_FROM.le(on))
            .and(CHILDREN_CLASS_ASSIGNMENTS.VALID_UNTIL.gt(on))
            .and(CHILDREN_ENROLMENTS.ENDED_AT.isNull())
            .and(CHILDREN_ENROLMENTS.VALID_FROM.le(on))
            .and(CHILDREN_ENROLMENTS.VALID_UNTIL.gt(on))
            .and(matchingIds == null ? DSL.noCondition() : CHILDREN_CLASS_ASSIGNMENTS.CHILD_ID.in(matchingIds));
        List<UUID> ids = db.selectDistinct(CHILDREN_CLASS_ASSIGNMENTS.CHILD_ID)
            .from(CHILDREN_CLASS_ASSIGNMENTS)
            .join(CHILDREN_ENROLMENTS)
            .on(CHILDREN_ENROLMENTS.ID.eq(CHILDREN_CLASS_ASSIGNMENTS.ENROLMENT_ID))
            .where(condition)
            .orderBy(CHILDREN_CLASS_ASSIGNMENTS.CHILD_ID)
            .limit(limit)
            .offset(offset)
            .fetch(CHILDREN_CLASS_ASSIGNMENTS.CHILD_ID);
        long count = db.select(DSL.countDistinct(CHILDREN_CLASS_ASSIGNMENTS.CHILD_ID))
            .from(CHILDREN_CLASS_ASSIGNMENTS)
            .join(CHILDREN_ENROLMENTS)
            .on(CHILDREN_ENROLMENTS.ID.eq(CHILDREN_CLASS_ASSIGNMENTS.ENROLMENT_ID))
            .where(condition)
            .fetchSingle()
            .value1();
        return new ExpectedPage(ids, count);
    }
    public AttendancePage attendance(UUID sessionId, boolean collected, Collection<UUID> matchingIds, int limit,
        int offset) {
        Condition condition = CHILDREN_ATTENDANCE.SESSION_ID.eq(sessionId)
            .and(collected ? CHILDREN_ATTENDANCE.CHECKED_OUT_AT.isNotNull()
                : CHILDREN_ATTENDANCE.CHECKED_OUT_AT.isNull())
            .and(matchingIds == null ? DSL.noCondition() : CHILDREN_ATTENDANCE.CHILD_ID.in(matchingIds));
        long total = db.fetchCount(CHILDREN_ATTENDANCE, condition);
        List<Attendance> values = db.selectFrom(CHILDREN_ATTENDANCE)
            .where(condition)
            .orderBy(CHILDREN_ATTENDANCE.ARRIVED_AT.desc(), CHILDREN_ATTENDANCE.ID)
            .limit(limit)
            .offset(offset)
            .fetch(ChildrenRecords::attendance);
        return new AttendancePage(values, total);
    }
    public Map<UUID, Attendance> current(UUID sessionId, Collection<UUID> childIds) {
        return db.selectFrom(CHILDREN_ATTENDANCE)
            .where(CHILDREN_ATTENDANCE.SESSION_ID.eq(sessionId))
            .and(CHILDREN_ATTENDANCE.CHILD_ID.in(childIds))
            .and(CHILDREN_ATTENDANCE.CHECKED_OUT_AT.isNull())
            .fetchMap(CHILDREN_ATTENDANCE.CHILD_ID, ChildrenRecords::attendance);
    }
}
