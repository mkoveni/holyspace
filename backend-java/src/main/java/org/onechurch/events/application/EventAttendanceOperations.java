package org.onechurch.events.application;
import org.onechurch.events.application.contract.AttendanceReader;
import org.onechurch.events.application.contract.EventJournal;
import org.onechurch.events.application.contract.EventJournalReader;
import org.onechurch.events.application.contract.EventReader;
import org.onechurch.events.application.contract.RegistrationReader;
import org.onechurch.events.domain.AdmissionWindow;
import org.onechurch.events.domain.Attendance;
import org.onechurch.events.domain.EventException;
import org.onechurch.events.domain.EventOccurrence;
import org.onechurch.events.domain.EventRegistration;
import org.onechurch.events.domain.EventRules;
import org.onechurch.events.domain.repository.AttendanceRepository;
import org.onechurch.events.domain.repository.OccurrenceRepository;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.util.Optional;
import java.util.UUID;
public final class EventAttendanceOperations {
    public record WalkIn(UUID registrationId, boolean admitted, String status) {}
    private final AttendanceReader reader;
    private final AttendanceRepository repository;
    private final RegistrationReader registrations;
    private final OccurrenceRepository occurrences;
    private final EventReader events;
    private final PersonDirectory people;
    private final EventJournal journal;
    private final EventJournalReader requests;
    private final EventRegistrationOperations bookings;
    private final UnitOfWork tx;
    private final Clock clock;
    public EventAttendanceOperations(AttendanceReader reader, AttendanceRepository repository,
        RegistrationReader registrations, OccurrenceRepository occurrences, EventReader events, PersonDirectory people,
        EventJournal journal, EventJournalReader requests, EventRegistrationOperations bookings, UnitOfWork tx,
        Clock clock) {
        this.reader = reader;
        this.repository = repository;
        this.registrations = registrations;
        this.occurrences = occurrences;
        this.events = events;
        this.people = people;
        this.journal = journal;
        this.requests = requests;
        this.bookings = bookings;
        this.tx = tx;
        this.clock = clock;
    }
    private EventRegistration registration(UUID id) {
        return registrations.registration(id).orElseThrow(() -> EventException.missing("Registration"));
    }
    private EventOccurrence lock(EventRegistration registration) {
        people.lock(registration.personId()).orElseThrow(() -> EventException.missing("Person"));
        return occurrences.lockOccurrence(registration.occurrenceId())
            .orElseThrow(() -> EventException.missing("Occurrence"));
    }
    private void admission(EventRegistration registration, EventOccurrence occurrence) {
        AdmissionWindow.require(occurrence, clock.instant());
        if (!registration.status().equals("CONFIRMED")
            || !events.event(occurrence.eventId()).map(e -> e.active()).orElse(false)
            || !people.get(registration.personId()).map(PersonDirectory.Entry::available).orElse(false)) {
            throw EventException
                .conflict("Check-in requires a confirmed booking and an available person on an active event.");
        }
    }
    private Attendance arrival(UUID actor, EventRegistration registration) {
        Attendance attendance = new Attendance(UUID.randomUUID(), registration.id(), clock.instant(), "PRESENT", 0);
        repository.insert(attendance);
        repository.history(attendance.id(), actor, "CHECKED_IN", "Arrival recorded.", clock.instant());
        return attendance;
    }
    public UUID checkIn(UUID actor, UUID request, UUID registrationId, long expected) {
        EventRules.id(request);
        String hash = Fingerprint.of(actor, registrationId, expected);
        return tx.execute(() -> {
            EventRegistration before = registration(registrationId);
            EventOccurrence occurrence = lock(before);
            Optional<EventJournalReader.Request> previous = requests.request(request);
            if (previous.isPresent()) {
                EventJournalReader.Request old = previous.orElseThrow();
                if (!old.kind().equals("CHECK_IN") || !old.hash().equals(hash)) {
                    throw EventException.conflict("This request identifier was already used.");
                }
                return old.targetId();
            }
            EventRegistration current = registration(registrationId);
            EventRules.version(current.version(), expected);
            admission(current, occurrence);
            if (reader.forRegistration(registrationId).isPresent()) {
                throw EventException.conflict("Attendance already exists. Use its versioned correction action.");
            }
            Attendance attendance = arrival(actor, current);
            journal.request(
                new EventJournalReader.Request(request, "CHECK_IN", hash, attendance.id(), actor, clock.instant()));
            return attendance.id();
        });
    }
    public WalkIn walkIn(UUID actor, UUID request, UUID occurrence, UUID person, Long expected) {
        return tx.execute(() -> {
            boolean repeated = requests.request(request).isPresent();
            UUID registration = bookings.registerAtDoor(actor, request, occurrence, person, expected);
            EventRegistration booking = registration(registration);
            if (!repeated && booking.status().equals("CONFIRMED")) {
                admission(booking, occurrences.lockOccurrence(occurrence).orElseThrow());
                arrival(actor, booking);
            }
            boolean admitted =
                reader.forRegistration(registration).map(a -> a.status().equals("PRESENT")).orElse(false);
            return new WalkIn(registration, admitted, booking.status());
        });
    }
    public void correct(UUID actor, UUID registrationId, long expected, String status, String reason) {
        String note = EventRules.text(reason, 1000);
        tx.execute(() -> {
            EventRegistration booking = registration(registrationId);
            EventOccurrence occurrence = lock(booking);
            Attendance current =
                reader.forRegistration(registrationId).orElseThrow(() -> EventException.missing("Attendance"));
            EventRules.version(current.version(), expected);
            if (status == null || !java.util.Set.of("PRESENT", "VOIDED").contains(status)) {
                throw EventException.invalid("Choose present or voided attendance.");
            }
            if (status.equals("PRESENT") && occurrence.endsAt().isAfter(clock.instant())) {
                admission(registration(registrationId), occurrence);
            }
            if (!status.equals(current.status())) {
                repository.save(current.correct(status));
                repository.history(current.id(), actor, status.equals("PRESENT") ? "REINSTATED" : "VOIDED", note,
                    clock.instant());
            }
            return null;
        });
    }
}
