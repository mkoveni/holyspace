package org.onechurch.events.application.contract;
import org.onechurch.events.domain.EventDefinition;
import org.onechurch.events.domain.EventSeries;

import java.util.List;
import java.util.Optional;
import java.util.UUID;
public interface EventReader {
    List<EventDefinition> eventsByIds(List<UUID> ids);
    long seriesCount(UUID eventId);
    Optional<EventDefinition> event(UUID id);
    List<EventDefinition> events(String search, boolean includeArchived, int limit, int offset);
    long eventCount(String search, boolean includeArchived);
    Optional<EventSeries> series(UUID id);
    List<EventSeries> seriesFor(UUID eventId, int limit, int offset);
}
