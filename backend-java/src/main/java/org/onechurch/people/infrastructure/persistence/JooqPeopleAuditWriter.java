package org.onechurch.people.infrastructure.persistence;

import static org.onechurch.people.infrastructure.persistence.PeopleRecordMapper.time;
import static org.onechurch.shared.infrastructure.jooq.Tables.PEOPLE_AUDIT;

import org.jooq.DSLContext;
import org.onechurch.people.application.contract.PeopleAuditWriter;
import org.springframework.stereotype.Repository;

import java.time.Instant;
import java.util.UUID;

@Repository
public class JooqPeopleAuditWriter implements PeopleAuditWriter {

    private final DSLContext db;

    public JooqPeopleAuditWriter(DSLContext db) {
        this.db = db;
    }

    @Override
    public void audit(UUID actorId, String action, String targetReference, Instant at) {
        db.insertInto(PEOPLE_AUDIT)
            .set(PEOPLE_AUDIT.ID, UUID.randomUUID())
            .set(PEOPLE_AUDIT.ACTOR_ID, actorId)
            .set(PEOPLE_AUDIT.ACTION, action)
            .set(PEOPLE_AUDIT.TARGET_REFERENCE, targetReference)
            .set(PEOPLE_AUDIT.OCCURRED_AT, time(at))
            .execute();
    }
}
