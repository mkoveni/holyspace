package org.onechurch.events.application;
import org.onechurch.events.application.contract.AttendanceReader;
import org.onechurch.events.application.contract.EventReader;
import org.onechurch.events.application.contract.OccurrenceMovementPolicy;
import org.onechurch.events.application.contract.OccurrenceReader;
import org.onechurch.events.application.contract.RegistrationReader;
import org.onechurch.events.application.contract.VenueReader;
import org.onechurch.events.domain.EventException;
import org.onechurch.events.domain.EventOccurrence;
import org.onechurch.events.domain.EventRules;
import org.onechurch.events.domain.EventSchedule;
import org.onechurch.events.domain.EventSeries;
import org.onechurch.events.domain.Venue;

import java.time.Clock;
import java.time.Instant;
import java.time.LocalDate;
import java.time.LocalTime;
import java.util.ArrayList;
import java.util.Comparator;
import java.util.List;
import java.util.Set;
import java.util.UUID;
public final class OccurrenceChangeReview {
    public record Draft(UUID occurrenceId, long expectedVersion, String action, String scope, LocalDate date,
        LocalTime startsAt, LocalTime endsAt, int endDayOffset, UUID venueId) {}
    public record Item(EventOccurrence before, EventOccurrence after, RegistrationReader.Counts registrations,
        long registrationVersion, long attended) {}
    public record Preview(String hash, List<Item> changes, List<UUID> skipped, long confirmedAffected,
        long waitlistedAffected, long attendedAffected) {}
    private final EventReader events;
    private final VenueReader venues;
    private final OccurrenceReader occurrences;
    private final RegistrationReader registrations;
    private final Clock clock;
    private final AttendanceReader attendance;
    private final List<OccurrenceMovementPolicy> movementPolicies;
    public OccurrenceChangeReview(EventReader events, VenueReader venues, OccurrenceReader occurrences,
        RegistrationReader registrations, Clock clock, AttendanceReader attendance,
        List<OccurrenceMovementPolicy> movementPolicies) {
        this.events = events;
        this.venues = venues;
        this.occurrences = occurrences;
        this.registrations = registrations;
        this.clock = clock;
        this.attendance = attendance;
        this.movementPolicies = movementPolicies;
    }
    public Preview preview(Draft draft) {
        if (draft == null || draft.action() == null
            || !Set.of("RESCHEDULE", "CANCEL", "RESTORE").contains(draft.action()) || draft.scope() == null
            || !Set.of("THIS", "FUTURE").contains(draft.scope())
            || (draft.action().equals("RESTORE") && !draft.scope().equals("THIS"))) {
            throw EventException.invalid(
                "Choose reschedule, cancel or restore and an explicit scope. Restoration applies to one occurrence.");
        }
        EventOccurrence pivot =
            occurrences.occurrence(draft.occurrenceId()).orElseThrow(() -> EventException.missing("Occurrence"));
        EventRules.version(pivot.version(), draft.expectedVersion());
        events.event(pivot.eventId())
            .filter(e -> e.active())
            .orElseThrow(() -> EventException.conflict("The event is archived."));
        EventSeries series = events.series(pivot.seriesId()).orElseThrow();
        Instant now = clock.instant();
        if (!pivot.endsAt().isAfter(now) || (!draft.action().equals("CANCEL") && !pivot.startsAt().isAfter(now))) {
            throw EventException.conflict("Historical or already-started scheduling cannot be rewritten.");
        }
        if (draft.action().equals("RESTORE") != pivot.status().equals("CANCELLED")) {
            throw EventException.conflict("Choose an action matching the current occurrence state.");
        }
        List<EventOccurrence> candidates = draft.scope().equals("THIS") ? List.of(pivot)
            : occurrences.futureSeries(series.id(), pivot.originDate(), now, 105);
        if (candidates.size() > 104) {
            throw EventException.conflict("Review at most 104 dates together.");
        }
        Venue target = draft.action().equals("RESCHEDULE") ? venues.venue(draft.venueId())
            .filter(Venue::active)
            .orElseThrow(() -> EventException.missing("Active venue")) : null;
        List<UUID> skipped = new ArrayList<>();
        List<Item> changes = new ArrayList<>();
        for (EventOccurrence current : candidates) {
            if ((draft.action().equals("RESCHEDULE") && (!current.status().equals("SCHEDULED")
                || !current.startsAt().isAfter(now) || (draft.scope().equals("FUTURE") && current.overridden())))
                || (draft.action().equals("CANCEL") && current.status().equals("CANCELLED"))) {
                skipped.add(current.id());
                continue;
            }
            if (draft.action().equals("RESCHEDULE")
                && movementPolicies.stream().anyMatch(policy -> !policy.movable(current.id()))) {
                throw EventException.conflict(
                    "A linked group has prepared its dated roster. Cancel this gathering instead of moving its attendance evidence.");
            }
            long attended = attendance.counts(current.id(), now).attended();
            if (draft.action().equals("RESCHEDULE") && attended > 0) {
                throw EventException.conflict(
                    "People have already checked in. Correct mistaken arrivals or cancel the occurrence instead of rescheduling it.");
            }
            EventOccurrence changed = switch (draft.action()) {
                case "RESCHEDULE" -> current.reschedule(
                    EventSchedule.window(draft.scope().equals("THIS") ? draft.date() : current.originDate(),
                        draft.startsAt(), draft.endsAt(), draft.endDayOffset(), current.timeZone()),
                    target, draft.scope().equals("THIS"));
                case "CANCEL" -> current.cancel();
                case "RESTORE" -> current.restore();
                default -> throw EventException.invalid("Choose an action.");
            };
            if (!draft.action().equals("CANCEL") && !changed.startsAt().isAfter(now)) {
                throw EventException.invalid("The revised occurrence must start in the future.");
            }
            changes.add(new Item(current, changed, registrations.counts(current.id()),
                registrations.policy(current.id()).orElseThrow().version(), attended));
        }
        if (changes.isEmpty()) {
            throw EventException.conflict(
                "No eligible dates remain. Individually edited and cancelled dates are preserved when rescheduling a future series.");
        }
        List<UUID> ids = changes.stream().map(item -> item.before().id()).toList();
        List<EventOccurrence> scheduled = changes.stream()
            .map(Item::after)
            .filter(o -> o.status().equals("SCHEDULED"))
            .sorted(Comparator.comparing(EventOccurrence::startsAt))
            .toList();
        for (EventOccurrence o : scheduled) {
            venues.venue(o.venueId())
                .filter(Venue::active)
                .orElseThrow(() -> EventException
                    .conflict("The recorded venue is archived. Reopen that venue before restoring this occurrence."));
            if (o.exclusive() && occurrences.venueClash(o.venueId(), o.startsAt(), o.endsAt(), ids)) {
                throw EventException.conflict("The exclusive venue is already reserved during a revised date.");
            }
        }
        for (int a = 0; a < scheduled.size(); a++) {
            for (int b = a + 1; b < scheduled.size(); b++) {
                EventOccurrence first = scheduled.get(a), second = scheduled.get(b);
                if (first.exclusive() && first.venueId().equals(second.venueId())
                    && EventRules.overlaps(first.startsAt(), first.endsAt(), second.startsAt(), second.endsAt())) {
                    throw EventException.conflict("The revised dates overlap in the exclusive venue.");
                }
            }
        }
        String hash = Fingerprint.of(draft, series.version(), target, changes, skipped);
        return new Preview(hash, List.copyOf(changes), List.copyOf(skipped),
            changes.stream().mapToLong(item -> item.registrations().confirmed()).sum(),
            changes.stream().mapToLong(item -> item.registrations().waitlisted()).sum(),
            changes.stream().mapToLong(Item::attended).sum());
    }
}
