package org.onechurch.volunteering.infrastructure.persistence;

import static org.onechurch.shared.infrastructure.jooq.Tables.SERVING_AUDIT;

import org.jooq.DSLContext;
import org.onechurch.volunteering.application.contract.ServingAudit;
import org.springframework.stereotype.Repository;

import java.time.Instant;
import java.util.UUID;

@Repository
public class JooqServingAudit implements ServingAudit {
    private final DSLContext db;
    public JooqServingAudit(DSLContext db) {
        this.db = db;
    }
    public void record(UUID actor, String action, UUID target, String reason, Instant at) {
        db.insertInto(SERVING_AUDIT)
            .set(SERVING_AUDIT.ID, UUID.randomUUID())
            .set(SERVING_AUDIT.ACTOR_ID, actor)
            .set(SERVING_AUDIT.ACTION, action)
            .set(SERVING_AUDIT.TARGET_ID, target)
            .set(SERVING_AUDIT.REASON, reason)
            .set(SERVING_AUDIT.OCCURRED_AT, ServingRecords.time(at))
            .execute();
    }
}
