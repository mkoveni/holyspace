package org.onechurch.events.infrastructure;
import org.onechurch.events.application.contract.EventReader;
import org.onechurch.events.application.contract.OccurrenceReader;
import org.onechurch.events.domain.EventDefinition;
import org.onechurch.events.domain.EventOccurrence;
import org.onechurch.events.domain.EventRules;
import org.onechurch.events.domain.repository.OccurrenceRepository;
import org.onechurch.volunteering.application.contract.ActivitySource;
import org.onechurch.volunteering.application.contract.ServingActivityLocks;
import org.springframework.stereotype.Component;

import java.time.Instant;
import java.util.Comparator;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
@Component
public class EventServingActivities implements ActivitySource, ServingActivityLocks {
    private final EventReader events;
    private final OccurrenceReader occurrences;
    private final OccurrenceRepository repository;
    public EventServingActivities(EventReader events, OccurrenceReader occurrences, OccurrenceRepository repository) {
        this.events = events;
        this.occurrences = occurrences;
        this.repository = repository;
    }
    public String kind() {
        return "EVENT_OCCURRENCE";
    }
    public Optional<ActivitySource.Activity> find(UUID id) {
        return occurrences.occurrence(id).map(o -> activity(o, events.event(o.eventId()).orElse(null)));
    }
    private ActivitySource.Activity activity(EventOccurrence occurrence, EventDefinition definition) {
        Optional<EventDefinition> event = Optional.ofNullable(definition);
        return new ActivitySource.Activity(occurrence.id(), event.map(EventDefinition::title).orElse("Event"),
            occurrence.startsAt(), occurrence.endsAt(),
            event.filter(EventDefinition::active).isPresent() && occurrence.status().equals("SCHEDULED"),
            Long.toString(occurrence.scheduleRevision()), occurrence.venueName());
    }
    public List<ActivitySource.Activity> upcoming(Instant from, Instant until) {
        return upcoming(from, until, 100, 0);
    }
    public List<ActivitySource.Activity> upcoming(Instant from, Instant until, int limit, int offset) {
        EventRules.range(from, until);
        EventRules.page(limit, offset);
        List<EventOccurrence> page = occurrences.calendar(null, null, from, until, "SCHEDULED", limit, offset);
        java.util.Map<UUID, EventDefinition> definitions = events
            .eventsByIds(page.stream().map(EventOccurrence::eventId).distinct().toList())
            .stream()
            .collect(java.util.stream.Collectors.toMap(EventDefinition::id, java.util.function.Function.identity()));
        return page.stream()
            .map(o -> activity(o, definitions.get(o.eventId())))
            .filter(ActivitySource.Activity::active)
            .toList();
    }
    public void lock(List<ServingActivityLocks.Activity> activities) {
        activities.stream()
            .filter(a -> a.kind().equals(kind()))
            .map(ServingActivityLocks.Activity::id)
            .distinct()
            .sorted(Comparator.comparing(UUID::toString))
            .forEach(repository::lockOccurrence);
    }
}
