package org.onechurch.events.application;
import org.onechurch.events.application.contract.EventJournalReader;
import org.onechurch.events.application.contract.EventReader;
import org.onechurch.events.application.contract.OccurrenceReader;
import org.onechurch.events.application.contract.RegistrationReader;
import org.onechurch.events.application.contract.VenueReader;
import org.onechurch.events.domain.EventDefinition;
import org.onechurch.events.domain.EventException;
import org.onechurch.events.domain.EventOccurrence;
import org.onechurch.events.domain.EventRegistration;
import org.onechurch.events.domain.EventRules;
import org.onechurch.events.domain.EventSeries;
import org.onechurch.events.domain.RegistrationPolicy;
import org.onechurch.events.domain.Venue;
import org.onechurch.people.application.contract.PersonDirectory;

import java.time.Instant;
import java.util.List;
import java.util.Map;
import java.util.UUID;
import java.util.function.Function;
import java.util.stream.Collectors;
public final class EventViews {
    public record Events(List<EventDefinition> events, long total) {}
    public record Venues(List<Venue> venues, long total) {}
    public record Details(EventDefinition event, List<EventSeries> series, long totalSeries) {}
    public record CalendarItem(EventOccurrence occurrence, String title, String type,
        RegistrationReader.Counts registrations) {}
    public record Calendar(List<CalendarItem> occurrences, long total) {}
    public record Occurrence(EventOccurrence occurrence, String title, String type, EventSeries series,
        RegistrationPolicy registrationPolicy, RegistrationReader.Counts registrations) {}
    public record PersonRegistration(EventRegistration registration, String name) {}
    public record Registrations(List<PersonRegistration> registrations, long total, RegistrationReader.Counts counts) {}
    private final EventReader events;
    private final VenueReader venues;
    private final OccurrenceReader occurrences;
    private final RegistrationReader registrations;
    private final PersonDirectory people;
    private final EventJournalReader journal;
    public EventViews(EventReader events, VenueReader venues, OccurrenceReader occurrences,
        RegistrationReader registrations, PersonDirectory people, EventJournalReader journal) {
        this.events = events;
        this.venues = venues;
        this.occurrences = occurrences;
        this.registrations = registrations;
        this.people = people;
        this.journal = journal;
    }
    public Events events(String search, boolean archived, int limit, int offset) {
        EventRules.page(limit, offset);
        String term = EventRules.optional(search, 200);
        return new Events(events.events(term, archived, limit, offset), events.eventCount(term, archived));
    }
    public Venues venues(String search, boolean archived, int limit, int offset) {
        EventRules.page(limit, offset);
        String term = EventRules.optional(search, 150);
        return new Venues(venues.venues(term, archived, limit, offset), venues.venueCount(term, archived));
    }
    public Details event(UUID id, int limit, int offset) {
        EventRules.page(limit, offset);
        return new Details(events.event(id).orElseThrow(() -> EventException.missing("Event")),
            events.seriesFor(id, limit, offset), events.seriesCount(id));
    }
    public Calendar calendar(UUID event, String search, Instant from, Instant until, String status, int limit,
        int offset) {
        EventRules.page(limit, offset);
        EventRules.range(from, until);
        String term = EventRules.optional(search, 200);
        if (status != null && !List.of("SCHEDULED", "CANCELLED").contains(status)) {
            throw EventException.invalid("Choose scheduled or cancelled.");
        }
        List<EventOccurrence> page = occurrences.calendar(event, term, from, until, status, limit, offset);
        Map<UUID, EventDefinition> definitions =
            events.eventsByIds(page.stream().map(EventOccurrence::eventId).distinct().toList())
                .stream()
                .collect(Collectors.toMap(EventDefinition::id, Function.identity()));
        Map<UUID, RegistrationReader.Counts> counts =
            registrations.countsMany(page.stream().map(EventOccurrence::id).toList());
        return new Calendar(page.stream()
            .map(o -> new CalendarItem(o, definitions.get(o.eventId()).title(), definitions.get(o.eventId()).type(),
                counts.getOrDefault(o.id(), new RegistrationReader.Counts(0, 0, 0))))
            .toList(), occurrences.calendarCount(event, term, from, until, status));
    }
    public Occurrence occurrence(UUID id) {
        EventOccurrence o = occurrences.occurrence(id).orElseThrow(() -> EventException.missing("Occurrence"));
        EventDefinition event = events.event(o.eventId()).orElseThrow();
        return new Occurrence(o, event.title(), event.type(), events.series(o.seriesId()).orElseThrow(),
            registrations.policy(id).orElseThrow(), registrations.counts(id));
    }
    public Registrations registrations(UUID occurrence, String status, int limit, int offset) {
        EventRules.page(limit, offset);
        occurrences.occurrence(occurrence).orElseThrow(() -> EventException.missing("Occurrence"));
        if (status != null && !List.of("CONFIRMED", "WAITLISTED", "CANCELLED").contains(status)) {
            throw EventException.invalid("Choose a registration status.");
        }
        List<EventRegistration> page = registrations.registrations(occurrence, status, limit, offset);
        Map<UUID, String> names = people.getAll(page.stream().map(EventRegistration::personId).distinct().toList())
            .stream()
            .collect(Collectors.toMap(PersonDirectory.Entry::id, PersonDirectory.Entry::name));
        return new Registrations(page.stream()
            .map(r -> new PersonRegistration(r, names.getOrDefault(r.personId(), "Unavailable person")))
            .toList(), registrations.registrationCount(occurrence, status), registrations.counts(occurrence));
    }
    public List<EventJournalReader.OccurrenceEntry> occurrenceHistory(UUID id, int limit, int offset) {
        EventRules.page(limit, offset);
        occurrences.occurrence(id).orElseThrow(() -> EventException.missing("Occurrence"));
        return journal.occurrenceHistory(id, limit, offset);
    }
    public List<EventJournalReader.RegistrationEntry> registrationHistory(UUID id, int limit, int offset) {
        EventRules.page(limit, offset);
        registrations.registration(id).orElseThrow(() -> EventException.missing("Registration"));
        return journal.registrationHistory(id, limit, offset);
    }
}
