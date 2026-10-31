package org.onechurch.events.application.contract;
import org.onechurch.events.domain.Venue;

import java.util.List;
import java.util.Optional;
import java.util.UUID;
public interface VenueReader {
    Optional<Venue> venue(UUID id);
    List<Venue> venues(String search, boolean includeArchived, int limit, int offset);
    long venueCount(String search, boolean includeArchived);
}
