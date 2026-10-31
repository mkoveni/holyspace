package org.onechurch.childrenministry.participation.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_CLASSES;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_CLASS_ASSIGNMENTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_ENROLMENTS;

import org.jooq.Condition;
import org.jooq.DSLContext;
import org.jooq.impl.DSL;
import org.onechurch.childrenministry.participation.application.contract.ProgrammeRosterReader;
import org.onechurch.childrenministry.participation.application.query.listprogrammechildren.ProgrammeChildrenPage.Placement;
import org.onechurch.childrenministry.participation.domain.Enrolment;
import org.onechurch.childrenministry.shared.infrastructure.persistence.ChildrenRecords;
import org.springframework.stereotype.Repository;

import java.time.LocalDate;
import java.util.Collection;
import java.util.List;
import java.util.Map;
import java.util.UUID;
@Repository
public class JooqProgrammeRosterStore implements ProgrammeRosterReader {
    private final DSLContext db;
    public JooqProgrammeRosterStore(DSLContext db) {
        this.db = db;
    }
    public Page list(UUID programmeId, Collection<UUID> childIds, String status, LocalDate on, int limit, int offset) {
        Condition condition = CHILDREN_ENROLMENTS.PROGRAMME_ID.eq(programmeId)
            .and(childIds == null ? DSL.noCondition() : CHILDREN_ENROLMENTS.CHILD_ID.in(childIds));
        Condition live = CHILDREN_ENROLMENTS.ENDED_AT.isNull();
        condition = condition.and(switch (status) {
            case "ACTIVE" ->
                live.and(CHILDREN_ENROLMENTS.VALID_FROM.le(on)).and(CHILDREN_ENROLMENTS.VALID_UNTIL.gt(on));
            case "UPCOMING" -> live.and(CHILDREN_ENROLMENTS.VALID_FROM.gt(on));
            case "EXPIRED" -> live.and(CHILDREN_ENROLMENTS.VALID_UNTIL.le(on));
            case "ENDED" -> CHILDREN_ENROLMENTS.ENDED_AT.isNotNull();
            default -> DSL.noCondition();
        });
        long total = db.fetchCount(CHILDREN_ENROLMENTS, condition);
        List<Enrolment> rows = db.selectFrom(CHILDREN_ENROLMENTS)
            .where(condition)
            .orderBy(CHILDREN_ENROLMENTS.CREATED_AT.desc(), CHILDREN_ENROLMENTS.ID)
            .limit(limit)
            .offset(offset)
            .fetch(ChildrenRecords::enrolment);
        return new Page(rows, total);
    }
    public Map<UUID, List<Placement>> placements(Collection<UUID> enrolmentIds, LocalDate on) {
        return db.select(CHILDREN_CLASS_ASSIGNMENTS.ENROLMENT_ID, CHILDREN_CLASSES.ID, CHILDREN_CLASSES.NAME)
            .from(CHILDREN_CLASS_ASSIGNMENTS)
            .join(CHILDREN_CLASSES)
            .on(CHILDREN_CLASSES.ID.eq(CHILDREN_CLASS_ASSIGNMENTS.CLASS_ID))
            .where(CHILDREN_CLASS_ASSIGNMENTS.ENROLMENT_ID.in(enrolmentIds))
            .and(CHILDREN_CLASS_ASSIGNMENTS.ENDED_AT.isNull())
            .and(CHILDREN_CLASS_ASSIGNMENTS.VALID_FROM.le(on))
            .and(CHILDREN_CLASS_ASSIGNMENTS.VALID_UNTIL.gt(on))
            .orderBy(CHILDREN_CLASSES.NAME, CHILDREN_CLASSES.ID)
            .fetchGroups(CHILDREN_CLASS_ASSIGNMENTS.ENROLMENT_ID,
                row -> new Placement(row.get(CHILDREN_CLASSES.ID), row.get(CHILDREN_CLASSES.NAME)));
    }
}
