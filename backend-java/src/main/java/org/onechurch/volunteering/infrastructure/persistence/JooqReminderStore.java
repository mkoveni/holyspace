package org.onechurch.volunteering.infrastructure.persistence;

import static org.onechurch.shared.infrastructure.jooq.Tables.SERVING_REMINDERS;

import org.jooq.DSLContext;
import org.onechurch.volunteering.application.contract.ReminderReader;
import org.onechurch.volunteering.application.contract.ReminderWriter;
import org.springframework.stereotype.Repository;

import java.time.OffsetDateTime;
import java.time.ZoneOffset;
import java.util.Optional;
import java.util.UUID;

@Repository
public class JooqReminderStore implements ReminderReader, ReminderWriter {
    private final DSLContext db;
    public JooqReminderStore(DSLContext db) {
        this.db = db;
    }
    public Optional<Reminder> reminder(UUID id) {
        return db.selectFrom(SERVING_REMINDERS)
            .where(SERVING_REMINDERS.ID.eq(id))
            .fetchOptional(r -> new Reminder(r.getId(), r.getAssignmentId(), r.getAssignmentVersion(),
                r.getShiftVersion(), r.getContactId(), r.getReviewHash(), r.getVerificationEvidence(), r.getCreatedBy(),
                r.getCreatedAt().toInstant(), r.getDeliveryId()));
    }
    public void insert(Reminder r) {
        db.insertInto(SERVING_REMINDERS)
            .set(SERVING_REMINDERS.ID, r.id())
            .set(SERVING_REMINDERS.ASSIGNMENT_ID, r.assignmentId())
            .set(SERVING_REMINDERS.ASSIGNMENT_VERSION, r.assignmentVersion())
            .set(SERVING_REMINDERS.SHIFT_VERSION, r.shiftVersion())
            .set(SERVING_REMINDERS.CONTACT_ID, r.contactId())
            .set(SERVING_REMINDERS.REVIEW_HASH, r.reviewHash())
            .set(SERVING_REMINDERS.VERIFICATION_EVIDENCE, r.evidence())
            .set(SERVING_REMINDERS.CREATED_BY, r.createdBy())
            .set(SERVING_REMINDERS.CREATED_AT, OffsetDateTime.ofInstant(r.createdAt(), ZoneOffset.UTC))
            .set(SERVING_REMINDERS.DELIVERY_ID, r.deliveryId())
            .execute();
    }
}
