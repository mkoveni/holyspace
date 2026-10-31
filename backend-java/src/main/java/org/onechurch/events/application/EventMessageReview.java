package org.onechurch.events.application;
import org.onechurch.communications.application.contract.EmailTemplates;
import org.onechurch.events.application.contract.EventReader;
import org.onechurch.events.application.contract.OccurrenceReader;
import org.onechurch.events.application.contract.RegistrationReader;
import org.onechurch.events.domain.EventDefinition;
import org.onechurch.events.domain.EventException;
import org.onechurch.events.domain.EventOccurrence;
import org.onechurch.events.domain.EventRegistration;
import org.onechurch.events.domain.EventRules;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.people.application.contract.PersonEmailDirectory;

import java.time.Instant;
import java.time.format.DateTimeFormatter;
import java.util.List;
import java.util.Locale;
import java.util.Map;
import java.util.Set;
import java.util.UUID;
public final class EventMessageReview {
    public record Review(UUID registrationId, long registrationVersion, long scheduleRevision, long eventVersion,
        String purpose, UUID contactId, String recipient, String subject, String html, String hash,
        long templateVersion) {
        @Override
        public String toString() {
            return "Review[registrationId=" + registrationId + "]";
        }
    }
    private final RegistrationReader registrations;
    private final OccurrenceReader occurrences;
    private final EventReader events;
    private final PersonDirectory people;
    private final PersonEmailDirectory emails;
    private final EmailTemplates templates;
    public EventMessageReview(RegistrationReader registrations, OccurrenceReader occurrences, EventReader events,
        PersonDirectory people, PersonEmailDirectory emails, EmailTemplates templates) {
        this.registrations = registrations;
        this.occurrences = occurrences;
        this.events = events;
        this.people = people;
        this.emails = emails;
        this.templates = templates;
    }
    public static String template(String purpose) {
        if (purpose == null || !Set.of("CONFIRMATION", "REMINDER", "CANCELLATION", "RESCHEDULED").contains(purpose)) {
            throw EventException.invalid("Choose a confirmation, reminder, cancellation or rescheduling notice.");
        }
        return "events." + purpose.toLowerCase(Locale.ROOT);
    }
    public static boolean allowed(EventRegistration booking, EventOccurrence occurrence, EventDefinition event,
        String purpose, Instant now) {
        if (purpose.equals("CANCELLATION")) {
            return booking.status().equals("CANCELLED") && now.isBefore(occurrence.endsAt().plusSeconds(30L * 86400));
        }
        return event.active() && booking.status().equals("CONFIRMED") && occurrence.status().equals("SCHEDULED")
            && occurrence.startsAt().isAfter(now)
            && (!purpose.equals("RESCHEDULED") || occurrence.scheduleRevision() > 0);
    }
    public List<PersonEmailDirectory.Email> contacts(UUID registration) {
        EventRegistration booking =
            registrations.registration(registration).orElseThrow(() -> EventException.missing("Registration"));
        return emails.active(booking.personId());
    }
    public Review review(UUID registration, UUID contact, String purpose, String message, Instant now) {
        String key = template(purpose);
        String note = EventRules.text(message, 2000);
        EventRegistration booking =
            registrations.registration(registration).orElseThrow(() -> EventException.missing("Registration"));
        EventOccurrence occurrence = occurrences.occurrence(booking.occurrenceId()).orElseThrow();
        EventDefinition event = events.event(occurrence.eventId()).orElseThrow();
        PersonDirectory.Entry person = people.get(booking.personId())
            .filter(PersonDirectory.Entry::available)
            .orElseThrow(() -> EventException.conflict("The person is unavailable."));
        if (!allowed(booking, occurrence, event, purpose, now)) {
            throw EventException.conflict(
                "This notice does not match the current booking or occurrence. Confirmations, reminders and changes require a confirmed upcoming booking; cancellations require a cancelled booking within 30 days of the occurrence.");
        }
        String recipient = emails.active(booking.personId())
            .stream()
            .filter(c -> c.id().equals(contact))
            .map(PersonEmailDirectory.Email::value)
            .findFirst()
            .orElseThrow(() -> EventException.conflict("Choose a current email address."));
        DateTimeFormatter format = DateTimeFormatter.ofPattern("EEE, d MMM uuuu HH:mm", Locale.ENGLISH)
            .withZone(EventRules.zone(occurrence.timeZone()));
        EmailTemplates.Rendered content = templates.render(key,
            Map.of("name", person.name(), "event", event.title(), "startsAt", format.format(occurrence.startsAt()),
                "endsAt", format.format(occurrence.endsAt()), "timeZone", occurrence.timeZone(), "venue",
                occurrence.venueName(), "message", note));
        String hash = hash(booking, occurrence, event.version(), purpose, contact, recipient, content.subject(),
            content.html(), content.version());
        return new Review(booking.id(), booking.version(), occurrence.scheduleRevision(), event.version(), purpose,
            contact, recipient, content.subject(), content.html(), hash, content.version());
    }
    public static String hash(EventRegistration booking, EventOccurrence occurrence, long eventVersion, String purpose,
        UUID contact, String recipient, String subject, String html, long template) {
        return Fingerprint.of(booking.id(), booking.version(), occurrence.scheduleRevision(), eventVersion, purpose,
            contact, recipient, subject, html, template);
    }
}
