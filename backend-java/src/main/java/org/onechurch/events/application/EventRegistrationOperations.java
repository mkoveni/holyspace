package org.onechurch.events.application;
import org.onechurch.events.application.contract.AttendanceReader;
import org.onechurch.events.application.contract.EventJournal;
import org.onechurch.events.application.contract.EventJournalReader;
import org.onechurch.events.application.contract.EventReader;
import org.onechurch.events.application.contract.OccurrenceReader;
import org.onechurch.events.application.contract.RegistrationReader;
import org.onechurch.events.domain.EventDefinition;
import org.onechurch.events.domain.EventException;
import org.onechurch.events.domain.EventOccurrence;
import org.onechurch.events.domain.EventRegistration;
import org.onechurch.events.domain.EventRules;
import org.onechurch.events.domain.RegistrationPolicy;
import org.onechurch.events.domain.repository.OccurrenceRepository;
import org.onechurch.events.domain.repository.RegistrationRepository;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;
import java.util.Optional;
import java.util.UUID;
public final class EventRegistrationOperations {
    private final EventReader eventReader;
    private final OccurrenceRepository occurrences;
    private final OccurrenceReader occurrenceReader;
    private final RegistrationRepository registrations;
    private final RegistrationReader reader;
    private final PersonDirectory people;
    private final EventJournal journal;
    private final EventJournalReader requests;
    private final UnitOfWork tx;
    private final Clock clock;
    private final AttendanceReader attendance;
    public EventRegistrationOperations(EventReader eventReader, OccurrenceRepository occurrences,
        OccurrenceReader occurrenceReader, RegistrationRepository registrations, RegistrationReader reader,
        PersonDirectory people, EventJournal journal, EventJournalReader requests, UnitOfWork tx, Clock clock,
        AttendanceReader attendance) {
        this.eventReader = eventReader;
        this.occurrences = occurrences;
        this.occurrenceReader = occurrenceReader;
        this.registrations = registrations;
        this.reader = reader;
        this.people = people;
        this.journal = journal;
        this.requests = requests;
        this.tx = tx;
        this.clock = clock;
        this.attendance = attendance;
    }
    private EventOccurrence lock(UUID id) {
        return occurrences.lockOccurrence(id).orElseThrow(() -> EventException.missing("Occurrence"));
    }
    private void available(EventOccurrence occurrence) {
        if (!occurrence.status().equals("SCHEDULED") || !occurrence.startsAt().isAfter(clock.instant())
            || !eventReader.event(occurrence.eventId()).filter(EventDefinition::active).isPresent()) {
            throw EventException
                .conflict("Registration requires an active event and a scheduled occurrence that has not started.");
        }
    }
    private void promote(UUID actor, RegistrationPolicy policy) {
        long confirmed = reader.counts(policy.occurrenceId()).confirmed();
        int places = policy.capacity() == null ? Integer.MAX_VALUE : (int) Math.max(0, policy.capacity() - confirmed);
        registrations.promote(policy.occurrenceId(), places, actor, clock.instant());
    }
    public void configure(UUID actor, UUID occurrenceId, long expected, boolean open, Integer capacity,
        boolean waitlist, String reason) {
        String note = EventRules.text(reason, 1000);
        tx.execute(() -> {
            EventOccurrence occurrence = lock(occurrenceId);
            RegistrationPolicy policy = reader.policy(occurrenceId).orElseThrow();
            EventRules.version(policy.version(), expected);
            if (open && (!occurrence.status().equals("SCHEDULED") || !occurrence.endsAt().isAfter(clock.instant())
                || !eventReader.event(occurrence.eventId()).map(EventDefinition::active).orElse(false))) {
                throw EventException
                    .conflict("Registration settings require an active scheduled occurrence that has not ended.");
            }
            if (capacity != null && capacity < reader.counts(occurrenceId).confirmed()) {
                throw EventException.conflict("Capacity cannot be below the confirmed registrations.");
            }
            RegistrationPolicy changed = policy.configure(open, capacity, waitlist);
            registrations.savePolicy(changed);
            if (occurrence.status().equals("SCHEDULED") && occurrence.endsAt().isAfter(clock.instant())) {
                promote(actor, changed);
            }
            journal.audit(actor, "registration.policy.updated", occurrenceId, note, clock.instant());
            return null;
        });
    }
    public UUID register(UUID actor, UUID request, UUID occurrenceId, UUID person, Long expected) {
        return register(actor, request, occurrenceId, person, expected, false);
    }
    public UUID registerAtDoor(UUID actor, UUID request, UUID occurrenceId, UUID person, Long expected) {
        return register(actor, request, occurrenceId, person, expected, true);
    }
    private UUID register(UUID actor, UUID request, UUID occurrenceId, UUID person, Long expected, boolean atDoor) {
        EventRules.id(request);
        EventRules.id(person);
        String kind = atDoor ? "REGISTER_DOOR" : "REGISTER";
        String hash = Fingerprint.of(actor, occurrenceId, person, expected);
        return tx.execute(() -> {
            people.lock(person)
                .filter(PersonDirectory.Entry::available)
                .orElseThrow(() -> EventException.missing("Available person"));
            EventOccurrence occurrence = lock(occurrenceId);
            Optional<EventJournalReader.Request> previous = requests.request(request);
            if (previous.isPresent()) {
                EventJournalReader.Request old = previous.orElseThrow();
                if (!old.kind().equals(kind) || !old.hash().equals(hash)) {
                    throw EventException.conflict("The registration request identifier was already used.");
                }
                return old.targetId();
            }
            if (atDoor) {
                org.onechurch.events.domain.AdmissionWindow.require(occurrence, clock.instant());
                if (!eventReader.event(occurrence.eventId()).map(EventDefinition::active).orElse(false)) {
                    throw EventException.conflict("The event is archived.");
                }
            } else {
                available(occurrence);
            }
            RegistrationPolicy policy = reader.policy(occurrenceId).orElseThrow();
            if (!policy.open()) {
                throw EventException.conflict("Registration is closed.");
            }
            Optional<EventRegistration> old = reader.forPerson(occurrenceId, person);
            if (old.isPresent()) {
                if (expected == null) {
                    throw EventException
                        .conflict("This person already has a record. Use its versioned re-registration action.");
                }
                EventRules.version(old.orElseThrow().version(), expected);
                if (!old.orElseThrow().status().equals("CANCELLED")) {
                    throw EventException.conflict("This person is already registered.");
                }
            }
            promote(actor, policy);
            long confirmed = reader.counts(occurrenceId).confirmed();
            boolean full = policy.capacity() != null && confirmed >= policy.capacity();
            if (full && !policy.waitlistEnabled()) {
                throw EventException.conflict("The occurrence is full and its waitlist is disabled.");
            }
            Instant now = clock.instant();
            RegistrationPolicy allocated = policy.allocate();
            String state = full ? "WAITLISTED" : "CONFIRMED";
            EventRegistration registration = old.map(r -> r.rejoin(state, allocated.nextPosition(), now))
                .orElseGet(() -> new EventRegistration(UUID.randomUUID(), occurrenceId, person, state,
                    allocated.nextPosition(), 0, now, now));
            if (old.isPresent()) {
                registrations.saveRegistration(registration);
            } else {
                registrations.insertRegistration(registration);
            }
            registrations.savePolicy(allocated);
            journal.registration(actor, "REGISTERED", registration.id(), state, now);
            journal.request(new EventJournalReader.Request(request, kind, hash, registration.id(), actor, now));
            return registration.id();
        });
    }
    public void cancel(UUID actor, UUID id, long expected, String reason) {
        String note = EventRules.text(reason, 1000);
        tx.execute(() -> {
            EventRegistration before =
                reader.registration(id).orElseThrow(() -> EventException.missing("Registration"));
            people.lock(before.personId()).orElseThrow(() -> EventException.missing("Person"));
            EventOccurrence occurrence = lock(before.occurrenceId());
            if (!occurrence.endsAt().isAfter(clock.instant())) {
                throw EventException.conflict("Historical registration cannot be rewritten.");
            }
            EventRegistration current = reader.registration(id).orElseThrow();
            EventRules.version(current.version(), expected);
            if (attendance.forRegistration(id).map(a -> a.status().equals("PRESENT")).orElse(false)) {
                throw EventException.conflict("Correct the person's attendance before cancelling this booking.");
            }
            if (!current.status().equals("CANCELLED")) {
                registrations.saveRegistration(current.cancel(clock.instant()));
                RegistrationPolicy policy = reader.policy(occurrence.id()).orElseThrow();
                if (occurrence.status().equals("SCHEDULED") && occurrence.endsAt().isAfter(clock.instant())) {
                    promote(actor, policy);
                }
                registrations.savePolicy(policy.touch());
                journal.registration(actor, "CANCELLED", id, note, clock.instant());
            }
            return null;
        });
    }
}
