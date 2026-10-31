package org.onechurch.events.domain.repository;
import org.onechurch.events.domain.EventDefinition;
import org.onechurch.events.domain.EventSeries;

import java.util.Optional;
import java.util.UUID;
public interface EventRepository {
    Optional<EventDefinition> lockEvent(UUID id);
    Optional<EventSeries> lockSeries(UUID id);
    void insertEvent(EventDefinition event);
    void saveEvent(EventDefinition event);
    void insertSeries(EventSeries series);
    void saveSeries(EventSeries series);
}
