package org.onechurch.events.domain.repository;
import org.onechurch.events.domain.EventOccurrence;

import java.util.Optional;
import java.util.UUID;
public interface OccurrenceRepository {
    Optional<EventOccurrence> lockOccurrence(UUID id);
    void insertOccurrence(EventOccurrence occurrence);
    void saveOccurrence(EventOccurrence occurrence);
}
