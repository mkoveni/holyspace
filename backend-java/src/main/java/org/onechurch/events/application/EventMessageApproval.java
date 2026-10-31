package org.onechurch.events.application;
import org.onechurch.communications.application.contract.DeliverySnapshot;
import org.onechurch.communications.application.contract.EmailDeliveries;
import org.onechurch.events.application.contract.EventJournal;
import org.onechurch.events.application.contract.EventNotificationReader;
import org.onechurch.events.application.contract.EventNotificationWriter;
import org.onechurch.events.application.contract.RegistrationReader;
import org.onechurch.events.domain.EventException;
import org.onechurch.events.domain.EventRegistration;
import org.onechurch.events.domain.EventRules;
import org.onechurch.events.domain.repository.OccurrenceRepository;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;
import java.util.Optional;
import java.util.UUID;
public final class EventMessageApproval {
    private final EventMessageReview review;
    private final EventNotificationReader reader;
    private final EventNotificationWriter writer;
    private final RegistrationReader registrations;
    private final OccurrenceRepository occurrences;
    private final PersonDirectory people;
    private final EmailDeliveries emails;
    private final EventJournal journal;
    private final UnitOfWork tx;
    private final Clock clock;
    public EventMessageApproval(EventMessageReview review, EventNotificationReader reader,
        EventNotificationWriter writer, RegistrationReader registrations, OccurrenceRepository occurrences,
        PersonDirectory people, EmailDeliveries emails, EventJournal journal, UnitOfWork tx, Clock clock) {
        this.review = review;
        this.reader = reader;
        this.writer = writer;
        this.registrations = registrations;
        this.occurrences = occurrences;
        this.people = people;
        this.emails = emails;
        this.journal = journal;
        this.tx = tx;
        this.clock = clock;
    }
    public UUID approve(UUID actor, UUID request, UUID registration, UUID contact, String purpose, String message,
        String reviewHash, String verification) {
        EventRules.id(request);
        String evidence = EventRules.text(verification, 500);
        String requestHash = Fingerprint.of(actor, registration, contact, purpose, message, reviewHash, evidence);
        return tx.execute(() -> {
            EventRegistration booking =
                registrations.registration(registration).orElseThrow(() -> EventException.missing("Registration"));
            people.lock(booking.personId()).orElseThrow();
            occurrences.lockOccurrence(booking.occurrenceId()).orElseThrow();
            Optional<EventNotificationReader.Notification> previous = reader.notification(request);
            if (previous.isPresent()) {
                EventNotificationReader.Notification old = previous.orElseThrow();
                if (!old.requestHash().equals(requestHash)) {
                    throw EventException.conflict("This notification request was already used.");
                }
                return old.deliveryId();
            }
            Instant now = clock.instant();
            EventMessageReview.Review snapshot = review.review(registration, contact, purpose, message, now);
            if (!snapshot.hash().equals(reviewHash)) {
                throw EventException
                    .conflict("The booking, occurrence, email or template changed. Review the notice again.");
            }
            UUID delivery = UUID.randomUUID();
            writer.insert(new EventNotificationReader.Notification(request, registration,
                snapshot.registrationVersion(), snapshot.scheduleRevision(), snapshot.eventVersion(), purpose, contact,
                reviewHash, requestHash, evidence, actor, now, delivery));
            emails.queue("events",
                new DeliverySnapshot(delivery, request, null, contact, snapshot.recipient(), snapshot.subject(),
                    snapshot.html(), reviewHash, actor, now, now, "QUEUED", 0, "<" + delivery + "@onechurch.delivery>",
                    null, null, null, "", false, 0, "text/html", EventMessageReview.template(purpose),
                    snapshot.templateVersion()));
            journal.audit(actor, "notification.approved", request, "", now);
            return delivery;
        });
    }
}
