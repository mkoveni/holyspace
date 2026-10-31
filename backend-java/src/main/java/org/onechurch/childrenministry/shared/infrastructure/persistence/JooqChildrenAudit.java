package org.onechurch.childrenministry.shared.infrastructure.persistence;

import static org.onechurch.childrenministry.shared.infrastructure.persistence.ChildrenRecords.time;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_AUDIT;

import org.jooq.DSLContext;
import org.onechurch.childrenministry.shared.application.contract.ChildrenAudit;
import org.springframework.stereotype.Repository;

import java.time.Instant;
import java.util.UUID;
@Repository
public class JooqChildrenAudit implements ChildrenAudit {
    private final DSLContext db;
    public JooqChildrenAudit(DSLContext db) {
        this.db = db;
    }
    public UUID record(UUID actorId, String action, UUID targetId, String reason, Instant at) {
        UUID id = UUID.randomUUID();
        db.insertInto(CHILDREN_AUDIT)
            .set(CHILDREN_AUDIT.ID, id)
            .set(CHILDREN_AUDIT.ACTOR_ID, actorId)
            .set(CHILDREN_AUDIT.ACTION, action)
            .set(CHILDREN_AUDIT.TARGET_ID, targetId)
            .set(CHILDREN_AUDIT.REASON, reason == null ? "" : reason)
            .set(CHILDREN_AUDIT.OCCURRED_AT, time(at))
            .execute();
        return id;
    }
}
