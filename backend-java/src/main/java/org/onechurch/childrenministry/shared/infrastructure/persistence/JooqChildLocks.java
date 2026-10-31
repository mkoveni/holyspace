package org.onechurch.childrenministry.shared.infrastructure.persistence;

import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_CHILD_GUARDS;

import org.jooq.DSLContext;
import org.onechurch.childrenministry.shared.application.contract.ChildLocks;
import org.springframework.stereotype.Repository;

import java.util.UUID;
@Repository
public class JooqChildLocks implements ChildLocks {
    private final DSLContext db;
    public JooqChildLocks(DSLContext db) {
        this.db = db;
    }
    public void lock(UUID childId) {
        db.insertInto(CHILDREN_CHILD_GUARDS)
            .set(CHILDREN_CHILD_GUARDS.CHILD_ID, childId)
            .onConflictDoNothing()
            .execute();
        db.selectFrom(CHILDREN_CHILD_GUARDS)
            .where(CHILDREN_CHILD_GUARDS.CHILD_ID.eq(childId))
            .forUpdate()
            .fetchSingle();
    }
}
