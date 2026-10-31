package org.onechurch.events.infrastructure;
import org.onechurch.communications.application.contract.DeliveryEligibility;
import org.onechurch.communications.application.contract.DeliverySnapshot;
import org.onechurch.events.application.EventMessageReview;
import org.onechurch.events.application.contract.EventNotificationReader;
import org.onechurch.events.application.contract.EventReader;
import org.onechurch.events.application.contract.OccurrenceReader;
import org.onechurch.events.application.contract.RegistrationReader;
import org.onechurch.events.domain.EventDefinition;
import org.onechurch.events.domain.EventOccurrence;
import org.onechurch.events.domain.EventRegistration;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.identityaccess.application.contract.ResponsibleAccountDirectory;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.people.application.contract.PersonEmailDirectory;
import org.springframework.stereotype.Component;

import java.time.Instant;
import java.util.UUID;
@Component
public class EventNotificationEligibility implements DeliveryEligibility {
    private final PermissionChecker auth;
    private final ResponsibleAccountDirectory accounts;
    private final EventNotificationReader notifications;
    private final RegistrationReader registrations;
    private final OccurrenceReader occurrences;
    private final EventReader events;
    private final PersonDirectory people;
    private final PersonEmailDirectory emails;
    public EventNotificationEligibility(PermissionChecker auth, ResponsibleAccountDirectory accounts,
        EventNotificationReader notifications, RegistrationReader registrations, OccurrenceReader occurrences,
        EventReader events, PersonDirectory people, PersonEmailDirectory emails) {
        this.auth = auth;
        this.accounts = accounts;
        this.notifications = notifications;
        this.registrations = registrations;
        this.occurrences = occurrences;
        this.events = events;
        this.people = people;
        this.emails = emails;
    }
    public String sourceContext() {
        return "events";
    }
    public void authorize(DeliverySnapshot delivery, UUID actor, String operation) {
        auth.requirePermission(actor, "events.communications.send");
    }
    public boolean eligible(DeliverySnapshot delivery, Instant at) {
        if (!accounts.eligible(delivery.approvedBy(), "events.communications.send")
            || delivery.templateVersion() == null) {
            return false;
        }
        return notifications.notification(delivery.communicationId())
            .filter(n -> n.deliveryId().equals(delivery.id()) && n.createdBy().equals(delivery.approvedBy())
                && n.contactId().equals(delivery.contactId()) && n.reviewHash().equals(delivery.reviewHash())
                && EventMessageReview.template(n.purpose()).equals(delivery.templateKey()))
            .map(n -> {
                EventRegistration booking = registrations.registration(n.registrationId()).orElse(null);
                if (booking == null) {
                    return false;
                }
                EventOccurrence occurrence = occurrences.occurrence(booking.occurrenceId()).orElseThrow();
                EventDefinition event = events.event(occurrence.eventId()).orElseThrow();
                return booking.version() == n.registrationVersion()
                    && occurrence.scheduleRevision() == n.scheduleRevision() && event.version() == n.eventVersion()
                    && EventMessageReview.allowed(booking, occurrence, event, n.purpose(), at)
                    && people.get(booking.personId()).map(PersonDirectory.Entry::available).orElse(false)
                    && emails.active(booking.personId())
                        .stream()
                        .anyMatch(c -> c.id().equals(n.contactId()) && c.value().equals(delivery.recipient()))
                    && EventMessageReview
                        .hash(booking, occurrence, event.version(), n.purpose(), n.contactId(), delivery.recipient(),
                            delivery.subject(), delivery.body(), delivery.templateVersion())
                        .equals(delivery.reviewHash());
            })
            .orElse(false);
    }
}
