package org.onechurch.events.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_NOTIFICATIONS;

import org.jooq.DSLContext;
import org.onechurch.events.application.contract.EventNotificationReader;
import org.onechurch.events.application.contract.EventNotificationWriter;
import org.springframework.stereotype.Repository;

import java.util.Optional;
import java.util.UUID;
@Repository
public class JooqEventNotificationStore implements EventNotificationReader, EventNotificationWriter {
    private final DSLContext db;
    public JooqEventNotificationStore(DSLContext db) {
        this.db = db;
    }
    public Optional<Notification> notification(UUID id) {
        return db.selectFrom(EVENT_NOTIFICATIONS)
            .where(EVENT_NOTIFICATIONS.ID.eq(id))
            .fetchOptional(r -> new Notification(r.getId(), r.getRegistrationId(), r.getRegistrationVersion(),
                r.getScheduleRevision(), r.getEventVersion(), r.getPurpose(), r.getContactId(), r.getReviewHash(),
                r.getRequestHash(), r.getVerificationEvidence(), r.getCreatedBy(), r.getCreatedAt().toInstant(),
                r.getDeliveryId()));
    }
    public void insert(Notification n) {
        db.insertInto(EVENT_NOTIFICATIONS)
            .set(EVENT_NOTIFICATIONS.ID, n.id())
            .set(EVENT_NOTIFICATIONS.REGISTRATION_ID, n.registrationId())
            .set(EVENT_NOTIFICATIONS.REGISTRATION_VERSION, n.registrationVersion())
            .set(EVENT_NOTIFICATIONS.SCHEDULE_REVISION, n.scheduleRevision())
            .set(EVENT_NOTIFICATIONS.EVENT_VERSION, n.eventVersion())
            .set(EVENT_NOTIFICATIONS.PURPOSE, n.purpose())
            .set(EVENT_NOTIFICATIONS.CONTACT_ID, n.contactId())
            .set(EVENT_NOTIFICATIONS.REVIEW_HASH, n.reviewHash())
            .set(EVENT_NOTIFICATIONS.REQUEST_HASH, n.requestHash())
            .set(EVENT_NOTIFICATIONS.VERIFICATION_EVIDENCE, n.evidence())
            .set(EVENT_NOTIFICATIONS.CREATED_BY, n.createdBy())
            .set(EVENT_NOTIFICATIONS.CREATED_AT, EventRecords.time(n.createdAt()))
            .set(EVENT_NOTIFICATIONS.DELIVERY_ID, n.deliveryId())
            .execute();
    }
}
