package org.onechurch.identityaccess.infrastructure.persistence;

import static org.onechurch.shared.infrastructure.jooq.Tables.IDENTITY_ACCESS_AUDIT;

import org.jooq.DSLContext;
import org.onechurch.identityaccess.application.contract.IdentityAuditWriter;
import org.springframework.stereotype.Repository;

import java.time.Instant;
import java.time.OffsetDateTime;
import java.time.ZoneOffset;
import java.util.UUID;

@Repository
public class JooqIdentityAuditWriter implements IdentityAuditWriter {

    private final DSLContext db;

    public JooqIdentityAuditWriter(DSLContext db) {
        this.db = db;
    }

    @Override
    public void audit(UUID actorId, String action, UUID targetId, Instant now) {
        db.insertInto(IDENTITY_ACCESS_AUDIT)
            .set(IDENTITY_ACCESS_AUDIT.ID, UUID.randomUUID())
            .set(IDENTITY_ACCESS_AUDIT.ACTOR_ID, actorId)
            .set(IDENTITY_ACCESS_AUDIT.ACTION, action)
            .set(IDENTITY_ACCESS_AUDIT.TARGET_ID, targetId)
            .set(IDENTITY_ACCESS_AUDIT.OCCURRED_AT, time(now))
            .execute();
    }

    private static OffsetDateTime time(Instant instant) {
        return instant.atOffset(ZoneOffset.UTC);
    }
}
