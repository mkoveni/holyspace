package org.onechurch.events.application;
import org.onechurch.communications.application.contract.MemberNotifications;
import org.onechurch.events.application.contract.EventJournal;
import org.onechurch.events.application.contract.MemberEventReader;
import org.onechurch.events.application.contract.MemberEventWriter;
import org.onechurch.events.application.contract.OccurrenceReader;
import org.onechurch.events.application.contract.RegistrationReader;
import org.onechurch.events.domain.AdmissionPass;
import org.onechurch.events.domain.AdmissionWindow;
import org.onechurch.events.domain.EventException;
import org.onechurch.events.domain.EventOccurrence;
import org.onechurch.events.domain.EventRegistration;
import org.onechurch.events.domain.EventRules;
import org.onechurch.events.domain.repository.AdmissionPassRepository;
import org.onechurch.events.domain.repository.EventRepository;
import org.onechurch.events.domain.repository.OccurrenceRepository;
import org.onechurch.identityaccess.application.contract.AccountDirectory;
import org.onechurch.identityaccess.application.contract.CurrentActor;
import org.onechurch.identityaccess.application.contract.MemberAdmissionGuard;
import org.onechurch.identityaccess.application.contract.MemberIdentity;
import org.onechurch.identityaccess.application.contract.MemberIdentityGuard;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.identityaccess.application.contract.SessionReader;
import org.onechurch.identityaccess.application.contract.TokenCodec;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;
import java.util.UUID;
public final class MemberEventActions {
    public record Pass(String credential, Instant expiresAt) {
        @Override
        public String toString() {
            return "Pass[credential=redacted]";
        }
    }
    private final MemberIdentity identity;
    private final MemberIdentityGuard guard;
    private final MemberAdmissionGuard admissionGuard;
    private final MemberEventReader reader;
    private final MemberEventWriter writer;
    private final OccurrenceReader occurrences;
    private final OccurrenceRepository occurrenceRepository;
    private final EventRepository events;
    private final RegistrationReader registrations;
    private final EventRegistrationOperations bookings;
    private final EventAttendanceOperations attendance;
    private final AdmissionPassRepository passes;
    private final PersonDirectory people;
    private final CurrentActor current;
    private final PermissionChecker auth;
    private final TokenCodec tokens;
    private final SessionReader sessions;
    private final AccountDirectory accounts;
    private final EventJournal journal;
    private final UnitOfWork tx;
    private final Clock clock;
    private final MemberNotifications notifications;
    public MemberEventActions(MemberIdentity identity, MemberIdentityGuard guard, MemberAdmissionGuard admissionGuard,
        MemberEventReader reader, MemberEventWriter writer, OccurrenceReader occurrences,
        OccurrenceRepository occurrenceRepository, EventRepository events, RegistrationReader registrations,
        EventRegistrationOperations bookings, EventAttendanceOperations attendance, AdmissionPassRepository passes,
        PersonDirectory people, CurrentActor current, PermissionChecker auth, TokenCodec tokens, SessionReader sessions,
        AccountDirectory accounts, EventJournal journal, UnitOfWork tx, Clock clock,
        MemberNotifications notifications) {
        this.identity = identity;
        this.guard = guard;
        this.admissionGuard = admissionGuard;
        this.reader = reader;
        this.writer = writer;
        this.occurrences = occurrences;
        this.occurrenceRepository = occurrenceRepository;
        this.events = events;
        this.registrations = registrations;
        this.bookings = bookings;
        this.attendance = attendance;
        this.passes = passes;
        this.people = people;
        this.current = current;
        this.auth = auth;
        this.tokens = tokens;
        this.sessions = sessions;
        this.accounts = accounts;
        this.journal = journal;
        this.tx = tx;
        this.clock = clock;
        this.notifications = notifications;
    }
    private EventOccurrence eventLock(UUID id) {
        EventOccurrence occurrence =
            occurrences.occurrence(id).orElseThrow(() -> EventException.missing("Member event"));
        events.lockEvent(occurrence.eventId()).orElseThrow(() -> EventException.missing("Event"));
        return occurrence;
    }
    private EventRegistration own(UUID person, UUID occurrence) {
        return registrations.forPerson(occurrence, person).orElseThrow(() -> EventException.missing("Booking"));
    }
    public void configure(UUID actor, UUID event, boolean enabled, long expected) {
        auth.requirePermission(actor, "events.manage");
        tx.execute(() -> {
            events.lockEvent(event).orElseThrow(() -> EventException.missing("Event"));
            auth.requirePermission(actor, "events.manage");
            EventRules.version(reader.offer(event).version(), expected);
            writer.offer(event, enabled, expected + 1);
            journal.audit(actor, "event.member_offer.changed", event,
                enabled ? "Published to members" : "Withdrawn from member catalogue", clock.instant());
            return null;
        });
    }
    public UUID register(UUID actor, UUID request, UUID occurrence, Long expected) {
        identity.requirePerson(actor);
        return tx.execute(() -> {
            EventOccurrence before = eventLock(occurrence);
            UUID person = guard.lockPerson(actor);
            if (!reader.offer(before.eventId()).enabled()) {
                throw EventException.missing("Member event");
            }
            identity.requirePerson(actor);
            UUID bookingId = bookings.register(actor, request, occurrence, person, expected);
            EventRegistration booking = own(person, occurrence);
            notifications.publish(actor, MemberNotifications.Kind.EVENT_BOOKING_UPDATED,
                bookingId + ":" + booking.version());
            return bookingId;
        });
    }
    public void cancel(UUID actor, UUID occurrence, long expected) {
        identity.requirePerson(actor);
        tx.execute(() -> {
            eventLock(occurrence);
            UUID person = guard.lockPerson(actor);
            identity.requirePerson(actor);
            bookings.cancel(actor, own(person, occurrence).id(), expected, "Cancelled by the member.");
            EventRegistration booking = own(person, occurrence);
            notifications.publish(actor, MemberNotifications.Kind.EVENT_BOOKING_UPDATED,
                booking.id() + ":" + booking.version());
            return null;
        });
    }
    public Pass issue(UUID actor, UUID occurrence) {
        identity.requirePerson(actor);
        return tx.execute(() -> {
            eventLock(occurrence);
            UUID person = guard.lockPerson(actor);
            MemberAdmissionGuard.Identity member =
                admissionGuard.lock(actor).orElseThrow(() -> EventException.missing("Member"));
            people.lock(person)
                .filter(PersonDirectory.Entry::available)
                .orElseThrow(() -> EventException.missing("Person"));
            EventOccurrence event = occurrenceRepository.lockOccurrence(occurrence).orElseThrow();
            identity.requirePerson(actor);
            EventRegistration booking = own(person, occurrence);
            if (!booking.status().equals("CONFIRMED")) {
                throw EventException.conflict("A confirmed booking is required.");
            }
            AdmissionWindow.require(event, clock.instant());
            String token = tokens.generate();
            Instant expiry = clock.instant().plusSeconds(300);
            if (expiry.isAfter(event.endsAt())) {
                expiry = event.endsAt();
            }
            passes.insert(
                new AdmissionPass(tokens.hash(token), booking.id(), actor, current.current().orElseThrow().sessionId(),
                    member.version(), booking.version(), event.scheduleRevision(), expiry, null));
            return new Pass("divinecommunityspace:event:v1:" + token, expiry);
        });
    }
    public UUID scan(UUID actor, UUID occurrence, UUID request, String credential) {
        auth.requirePermission(actor, "events.attendance.manage");
        if (credential == null || !credential.matches("divinecommunityspace:event:v1:[A-Za-z0-9_-]{20,200}")) {
            throw EventException.invalid("Scan a Divine Community Space event attendance code.");
        }
        String hash = tokens.hash(credential.substring("divinecommunityspace:event:v1:".length()));
        AdmissionPass before = passes.find(hash).orElseThrow(() -> EventException.missing("Attendance pass"));
        return tx.execute(() -> {
            eventLock(occurrence);
            MemberAdmissionGuard.Identity member = admissionGuard.lock(before.accountId())
                .orElseThrow(() -> EventException.conflict("Member access is no longer active."));
            EventRegistration booking = registrations.registration(before.registrationId()).orElseThrow();
            if (!booking.occurrenceId().equals(occurrence) || !booking.personId().equals(member.personId())) {
                throw EventException.conflict("This code does not belong to this gathering.");
            }
            people.lock(member.personId())
                .filter(PersonDirectory.Entry::available)
                .orElseThrow(() -> EventException.missing("Person"));
            EventOccurrence event = occurrenceRepository.lockOccurrence(occurrence).orElseThrow();
            AdmissionPass pass = passes.lock(hash).orElseThrow();
            auth.requirePermission(actor, "events.attendance.manage");
            if (pass.usedAt() != null || !pass.expiresAt().isAfter(clock.instant())
                || member.version() != pass.memberVersion() || booking.version() != pass.registrationVersion()
                || event.scheduleRevision() != pass.scheduleRevision() || !accounts.active(pass.accountId())
                || sessions.findById(pass.sessionId())
                    .filter(s -> s.accountId().equals(pass.accountId()) && s.expiresAt().isAfter(clock.instant()))
                    .isEmpty()) {
                throw EventException.conflict("This attendance code expired or changed. Ask the member to refresh it.");
            }
            UUID arrival = attendance.checkIn(actor, request, booking.id(), pass.registrationVersion());
            passes.used(hash, clock.instant());
            return arrival;
        });
    }
}
