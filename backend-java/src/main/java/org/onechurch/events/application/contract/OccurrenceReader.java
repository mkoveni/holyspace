package org.onechurch.events.application.contract;
import org.onechurch.events.domain.EventOccurrence;

import java.time.Instant;
import java.time.LocalDate;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
public interface OccurrenceReader {
    Optional<EventOccurrence> occurrence(UUID id);
    default List<EventOccurrence> byIds(List<UUID> ids) {
        return ids.stream().map(this::occurrence).flatMap(Optional::stream).toList();
    }
    List<EventOccurrence> calendar(UUID eventId, String search, Instant from, Instant until, String status, int limit,
        int offset);
    long calendarCount(UUID eventId, String search, Instant from, Instant until, String status);
    List<EventOccurrence> futureSeries(UUID seriesId, LocalDate originFrom, Instant now, int limit);
    boolean venueClash(UUID venue, Instant from, Instant until, List<UUID> exclude);
    boolean hasFutureScheduled(UUID event, Instant now);
}
