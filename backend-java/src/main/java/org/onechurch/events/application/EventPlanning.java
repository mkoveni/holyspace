package org.onechurch.events.application;
import org.onechurch.events.application.contract.EventJournal;
import org.onechurch.events.application.contract.EventJournalReader;
import org.onechurch.events.application.contract.OccurrenceReader;
import org.onechurch.events.domain.EventDefinition;
import org.onechurch.events.domain.EventException;
import org.onechurch.events.domain.EventOccurrence;
import org.onechurch.events.domain.EventRules;
import org.onechurch.events.domain.EventSchedule;
import org.onechurch.events.domain.EventSeries;
import org.onechurch.events.domain.RegistrationPolicy;
import org.onechurch.events.domain.repository.EventRepository;
import org.onechurch.events.domain.repository.OccurrenceRepository;
import org.onechurch.events.domain.repository.RegistrationRepository;
import org.onechurch.events.domain.repository.VenueRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.util.Optional;
import java.util.UUID;
public final class EventPlanning {
    private final EventRepository events;
    private final VenueRepository venues;
    private final OccurrenceRepository occurrences;
    private final OccurrenceReader occurrenceReader;
    private final RegistrationRepository registrations;
    private final EventJournal journal;
    private final EventJournalReader requests;
    private final ScheduleReview review;
    private final UnitOfWork tx;
    private final Clock clock;
    public EventPlanning(EventRepository events, VenueRepository venues, OccurrenceRepository occurrences,
        OccurrenceReader occurrenceReader, RegistrationRepository registrations, EventJournal journal,
        EventJournalReader requests, ScheduleReview review, UnitOfWork tx, Clock clock) {
        this.events = events;
        this.venues = venues;
        this.occurrences = occurrences;
        this.occurrenceReader = occurrenceReader;
        this.registrations = registrations;
        this.journal = journal;
        this.requests = requests;
        this.review = review;
        this.tx = tx;
        this.clock = clock;
    }
    private Optional<UUID> previous(UUID request, String kind, String hash) {
        EventRules.id(request);
        return requests.request(request).map(old -> {
            if (!old.kind().equals(kind) || !old.hash().equals(hash)) {
                throw EventException.conflict("This request identifier was already used for different details.");
            }
            return old.targetId();
        });
    }
    public UUID create(UUID actor, UUID request, String title, String type, String description,
        ScheduleReview.Draft schedule, String previewHash) {
        EventDefinition validated = new EventDefinition(UUID.randomUUID(), title, type, description, true, 0);
        String hash = Fingerprint.of(actor, title, type, description, schedule, previewHash);
        return tx.execute(() -> {
            Optional<UUID> old = previous(request, "CREATE_EVENT", hash);
            if (old.isPresent()) {
                return old.orElseThrow();
            }
            if (schedule == null) {
                throw EventException.invalid("Choose a schedule.");
            }
            venues.lockVenue(schedule.venueId()).orElseThrow(() -> EventException.missing("Venue"));
            old = previous(request, "CREATE_EVENT", hash);
            if (old.isPresent()) {
                return old.orElseThrow();
            }
            ScheduleReview.Preview preview = review.preview(schedule);
            requireHash(preview.hash(), previewHash);
            events.insertEvent(validated);
            EventSeries series = schedule.series(UUID.randomUUID(), validated.id());
            events.insertSeries(series);
            insertDates(actor, series, preview);
            journal.request(
                new EventJournalReader.Request(request, "CREATE_EVENT", hash, validated.id(), actor, clock.instant()));
            journal.audit(actor, "event.created", validated.id(), "", clock.instant());
            return validated.id();
        });
    }
    public UUID addSeries(UUID actor, UUID request, UUID eventId, long expected, ScheduleReview.Draft schedule,
        String previewHash) {
        String hash = Fingerprint.of(actor, eventId, schedule, previewHash);
        return tx.execute(() -> {
            EventDefinition event = events.lockEvent(eventId)
                .filter(EventDefinition::active)
                .orElseThrow(() -> EventException.missing("Active event"));
            Optional<UUID> old = previous(request, "CREATE_SERIES", hash);
            if (old.isPresent()) {
                return old.orElseThrow();
            }
            EventRules.version(event.version(), expected);
            if (schedule == null) {
                throw EventException.invalid("Choose a schedule.");
            }
            venues.lockVenue(schedule.venueId()).orElseThrow(() -> EventException.missing("Venue"));
            ScheduleReview.Preview preview = review.preview(schedule);
            requireHash(preview.hash(), previewHash);
            EventSeries series = schedule.series(UUID.randomUUID(), event.id());
            events.insertSeries(series);
            insertDates(actor, series, preview);
            events.saveEvent(event.touch());
            journal.request(
                new EventJournalReader.Request(request, "CREATE_SERIES", hash, series.id(), actor, clock.instant()));
            journal.audit(actor, "series.created", series.id(), "", clock.instant());
            return series.id();
        });
    }
    private void insertDates(UUID actor, EventSeries series, ScheduleReview.Preview preview) {
        for (EventSchedule.Window window : preview.dates()) {
            EventOccurrence o =
                new EventOccurrence(UUID.randomUUID(), series.eventId(), series.id(), window.originDate(),
                    series.timeZone(), window.startsAt(), window.endsAt(), preview.venue().id(), preview.venue().name(),
                    preview.venue().address(), preview.venue().exclusive(), "SCHEDULED", false, 0, 0);
            occurrences.insertOccurrence(o);
            registrations.insertPolicy(new RegistrationPolicy(o.id(), false, null, true, 0, 0));
            journal.occurrence(actor, "SCHEDULED", o, "Occurrence scheduled.", clock.instant());
        }
    }
    public void update(UUID actor, UUID id, long expected, String title, String type, String description,
        boolean active, String reason) {
        String note = EventRules.text(reason, 1000);
        tx.execute(() -> {
            EventDefinition event = events.lockEvent(id).orElseThrow(() -> EventException.missing("Event"));
            EventRules.version(event.version(), expected);
            if (!active && occurrenceReader.hasFutureScheduled(id, clock.instant())) {
                throw EventException
                    .conflict("Cancel the remaining scheduled occurrences before archiving this event.");
            }
            events.saveEvent(event.edit(title, type, description, active));
            journal.audit(actor, "event.updated", id, note, clock.instant());
            return null;
        });
    }
    static void requireHash(String actual, String expected) {
        if (!actual.equals(expected)) {
            throw EventException
                .conflict("The reviewed schedule changed. Preview the current details before applying.");
        }
    }
}
