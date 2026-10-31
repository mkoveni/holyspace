package org.onechurch.volunteering.application.command.approvevolunteerreminder;

import org.onechurch.communications.application.contract.DeliverySnapshot;
import org.onechurch.communications.application.contract.EmailDeliveries;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.shared.application.UnitOfWork;
import org.onechurch.volunteering.application.ReminderReview;
import org.onechurch.volunteering.application.VolunteerProfiles;
import org.onechurch.volunteering.application.contract.ReminderReader;
import org.onechurch.volunteering.application.contract.ReminderWriter;
import org.onechurch.volunteering.application.contract.RosterReader;
import org.onechurch.volunteering.application.contract.ServingAudit;
import org.onechurch.volunteering.domain.Assignment;
import org.onechurch.volunteering.domain.ServingRules;
import org.onechurch.volunteering.domain.VolunteerException;
import org.onechurch.volunteering.domain.repository.RosterRepository;

import java.time.Clock;
import java.time.Instant;
import java.util.Optional;
import java.util.UUID;

public final class ApproveVolunteerReminderHandler {
    private final PermissionChecker auth;
    private final UnitOfWork tx;
    private final VolunteerProfiles profiles;
    private final RosterReader roster;
    private final RosterRepository repository;
    private final ReminderReview review;
    private final ReminderReader reader;
    private final ReminderWriter writer;
    private final EmailDeliveries emails;
    private final ServingAudit audit;
    private final Clock clock;
    public ApproveVolunteerReminderHandler(PermissionChecker auth, UnitOfWork tx, VolunteerProfiles profiles,
        RosterReader roster, RosterRepository repository, ReminderReview review, ReminderReader reader,
        ReminderWriter writer, EmailDeliveries emails, ServingAudit audit, Clock clock) {
        this.auth = auth;
        this.tx = tx;
        this.profiles = profiles;
        this.roster = roster;
        this.repository = repository;
        this.review = review;
        this.reader = reader;
        this.writer = writer;
        this.emails = emails;
        this.audit = audit;
        this.clock = clock;
    }
    public UUID handle(ApproveVolunteerReminderCommand c) {
        auth.requirePermission(c.actorId(), "volunteering.communications.send");
        String evidence = ServingRules.text(c.verificationEvidence(), 500);
        ServingRules.id(c.requestId());
        return tx.execute(() -> {
            Assignment a =
                roster.assignment(c.assignmentId()).orElseThrow(() -> VolunteerException.missing("Assignment"));
            profiles.lock(a.volunteerId());
            repository.lockShift(a.shiftId()).orElseThrow();
            repository.lockAssignment(a.id()).orElseThrow();
            Optional<ReminderReader.Reminder> previous = reader.reminder(c.requestId());
            if (previous.isPresent()) {
                ReminderReader.Reminder old = previous.orElseThrow();
                if (!old.assignmentId().equals(c.assignmentId()) || !old.contactId().equals(c.contactId())
                    || !old.createdBy().equals(c.actorId()) || !old.reviewHash().equals(c.reviewHash())
                    || !old.evidence().equals(evidence)) {
                    throw VolunteerException.conflict("This reminder request was already used.");
                }
                return old.deliveryId();
            }
            Instant now = clock.instant();
            ReminderReview.Review snapshot = review.review(c.assignmentId(), c.contactId(), c.message(), now);
            if (!snapshot.hash().equals(c.reviewHash())) {
                throw VolunteerException.conflict("The shift, contact or template changed. Review the reminder again.");
            }
            UUID deliveryId = UUID.randomUUID();
            writer.insert(new ReminderReader.Reminder(c.requestId(), a.id(), snapshot.assignmentVersion(),
                snapshot.shiftRevision(), c.contactId(), snapshot.hash(), evidence, c.actorId(), now, deliveryId));
            emails.queue("volunteering",
                new DeliverySnapshot(deliveryId, c.requestId(), null, c.contactId(), snapshot.recipient(),
                    snapshot.subject(), snapshot.html(), snapshot.hash(), c.actorId(), now, now, "QUEUED", 0,
                    "<" + deliveryId + "@onechurch.delivery>", null, null, null, "", false, 0, "text/html",
                    ReminderReview.TEMPLATE, snapshot.templateVersion()));
            audit.record(c.actorId(), "reminder.approved", c.requestId(), now);
            return deliveryId;
        });
    }
}
