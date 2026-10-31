package org.onechurch.events.domain.repository;
import org.onechurch.events.domain.Venue;

import java.util.Optional;
import java.util.UUID;
public interface VenueRepository {
    Optional<Venue> lockVenue(UUID id);
    void insertVenue(Venue venue);
    void saveVenue(Venue venue);
}
