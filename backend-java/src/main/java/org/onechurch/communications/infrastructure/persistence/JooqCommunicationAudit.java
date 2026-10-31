package org.onechurch.communications.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNICATION_AUDIT;

import org.jooq.DSLContext;
import org.onechurch.communications.application.contract.CommunicationAudit;
import org.springframework.stereotype.Repository;

import java.time.Instant;
import java.util.UUID;
@Repository
public class JooqCommunicationAudit implements CommunicationAudit {
    private final DSLContext db;
    public JooqCommunicationAudit(DSLContext db) {
        this.db = db;
    }
    public void record(UUID actor, String action, UUID target, String detail, Instant at) {
        db.insertInto(COMMUNICATION_AUDIT)
            .set(COMMUNICATION_AUDIT.ID, UUID.randomUUID())
            .set(COMMUNICATION_AUDIT.ACTOR_ID, actor)
            .set(COMMUNICATION_AUDIT.ACTION, action)
            .set(COMMUNICATION_AUDIT.TARGET_ID, target)
            .set(COMMUNICATION_AUDIT.OCCURRED_AT, CommunicationRecords.time(at))
            .execute();
    }
}
