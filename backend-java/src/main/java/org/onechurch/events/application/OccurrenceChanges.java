package org.onechurch.events.application;
import org.onechurch.events.application.contract.EventJournal;
import org.onechurch.events.application.contract.OccurrenceReader;
import org.onechurch.events.application.contract.OccurrenceScheduleListener;
import org.onechurch.events.application.contract.RegistrationReader;
import org.onechurch.events.domain.EventException;
import org.onechurch.events.domain.EventOccurrence;
import org.onechurch.events.domain.EventRules;
import org.onechurch.events.domain.EventSeries;
import org.onechurch.events.domain.RegistrationPolicy;
import org.onechurch.events.domain.repository.EventRepository;
import org.onechurch.events.domain.repository.OccurrenceRepository;
import org.onechurch.events.domain.repository.RegistrationRepository;
import org.onechurch.events.domain.repository.VenueRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.util.ArrayList;
import java.util.Comparator;
import java.util.List;
import java.util.UUID;
public final class OccurrenceChanges {
    private final EventRepository events;
    private final VenueRepository venues;
    private final OccurrenceRepository occurrences;
    private final OccurrenceReader reader;
    private final RegistrationRepository registrations;
    private final RegistrationReader registrationReader;
    private final EventJournal journal;
    private final OccurrenceChangeReview review;
    private final UnitOfWork tx;
    private final Clock clock;
    private final List<OccurrenceScheduleListener> listeners;
    public OccurrenceChanges(EventRepository events, VenueRepository venues, OccurrenceRepository occurrences,
        OccurrenceReader reader, RegistrationRepository registrations, RegistrationReader registrationReader,
        EventJournal journal, OccurrenceChangeReview review, UnitOfWork tx, Clock clock,
        List<OccurrenceScheduleListener> listeners) {
        this.events = events;
        this.venues = venues;
        this.occurrences = occurrences;
        this.reader = reader;
        this.registrations = registrations;
        this.registrationReader = registrationReader;
        this.journal = journal;
        this.review = review;
        this.tx = tx;
        this.clock = clock;
        this.listeners = listeners;
    }
    public void apply(UUID actor, String action, OccurrenceChangeReview.Draft draft, String hash, String reason) {
        String note = EventRules.text(reason, 1000);
        if (draft == null || !action.equals(draft.action())) {
            throw EventException.invalid("Use the matching explicit action.");
        }
        tx.execute(() -> {
            EventOccurrence before =
                reader.occurrence(draft.occurrenceId()).orElseThrow(() -> EventException.missing("Occurrence"));
            events.lockEvent(before.eventId()).orElseThrow();
            EventSeries series = events.lockSeries(before.seriesId()).orElseThrow();
            List<EventOccurrence> candidates = "FUTURE".equals(draft.scope())
                ? reader.futureSeries(series.id(), before.originDate(), clock.instant(), 105) : List.of(before);
            List<UUID> venueIds = new ArrayList<>(candidates.stream().map(EventOccurrence::venueId).toList());
            if (draft.venueId() != null) {
                venueIds.add(draft.venueId());
            }
            venueIds.stream()
                .distinct()
                .sorted(Comparator.comparing(UUID::toString))
                .forEach(id -> venues.lockVenue(id).orElseThrow(() -> EventException.missing("Venue")));
            candidates.stream()
                .map(EventOccurrence::id)
                .distinct()
                .sorted(Comparator.comparing(UUID::toString))
                .forEach(id -> occurrences.lockOccurrence(id).orElseThrow());
            OccurrenceChangeReview.Preview preview = review.preview(draft);
            EventPlanning.requireHash(preview.hash(), hash);
            for (OccurrenceChangeReview.Item item : preview.changes()) {
                occurrences.saveOccurrence(item.after());
                listeners.forEach(listener -> listener.changed(item.after().id(), item.after().startsAt()));
                if (action.equals("CANCEL")) {
                    registrations.cancelOccurrenceRegistrations(item.after().id(), actor, note, clock.instant());
                    RegistrationPolicy policy = registrationReader.policy(item.after().id()).orElseThrow();
                    registrations.savePolicy(policy.configure(false, policy.capacity(), policy.waitlistEnabled()));
                }
                journal.occurrence(actor, action, item.after(), note, clock.instant());
            }
            EventSeries updated = action.equals("RESCHEDULE") && "FUTURE".equals(draft.scope())
                ? series.defaults(draft.startsAt(), draft.endsAt(), draft.endDayOffset(), draft.venueId())
                : series.touch();
            events.saveSeries(updated);
            journal.audit(actor, "occurrences." + action.toLowerCase(java.util.Locale.ROOT), before.eventId(), note,
                clock.instant());
            return null;
        });
    }
}
