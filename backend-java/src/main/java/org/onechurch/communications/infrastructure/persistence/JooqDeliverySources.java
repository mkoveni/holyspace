package org.onechurch.communications.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNICATION_DELIVERY_SOURCES;

import org.jooq.DSLContext;
import org.onechurch.communications.application.contract.DeliverySources;
import org.springframework.stereotype.Repository;

import java.util.UUID;
@Repository
public class JooqDeliverySources implements DeliverySources {
    private final DSLContext db;
    public JooqDeliverySources(DSLContext db) {
        this.db = db;
    }
    public String context(UUID id) {
        return db.select(COMMUNICATION_DELIVERY_SOURCES.SOURCE_CONTEXT)
            .from(COMMUNICATION_DELIVERY_SOURCES)
            .where(COMMUNICATION_DELIVERY_SOURCES.DELIVERY_ID.eq(id))
            .fetchOne(COMMUNICATION_DELIVERY_SOURCES.SOURCE_CONTEXT);
    }
    public void associate(UUID id, String context) {
        db.insertInto(COMMUNICATION_DELIVERY_SOURCES)
            .set(COMMUNICATION_DELIVERY_SOURCES.DELIVERY_ID, id)
            .set(COMMUNICATION_DELIVERY_SOURCES.SOURCE_CONTEXT, context)
            .execute();
    }
}
