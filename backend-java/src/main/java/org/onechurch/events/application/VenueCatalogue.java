package org.onechurch.events.application;
import org.onechurch.events.application.contract.EventJournal;
import org.onechurch.events.domain.EventException;
import org.onechurch.events.domain.EventRules;
import org.onechurch.events.domain.Venue;
import org.onechurch.events.domain.repository.VenueRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.util.UUID;
public final class VenueCatalogue {
    private final VenueRepository venues;
    private final EventJournal journal;
    private final UnitOfWork tx;
    private final Clock clock;
    public VenueCatalogue(VenueRepository venues, EventJournal journal, UnitOfWork tx, Clock clock) {
        this.venues = venues;
        this.journal = journal;
        this.tx = tx;
        this.clock = clock;
    }
    public UUID create(UUID actor, String name, String address, boolean exclusive) {
        return tx.execute(() -> {
            Venue venue = new Venue(UUID.randomUUID(), name, address, exclusive, true, 0);
            venues.insertVenue(venue);
            journal.audit(actor, "venue.created", venue.id(), "", clock.instant());
            return venue.id();
        });
    }
    public void update(UUID actor, UUID id, long expected, String name, String address, boolean active) {
        tx.execute(() -> {
            Venue current = venues.lockVenue(id).orElseThrow(() -> EventException.missing("Venue"));
            EventRules.version(current.version(), expected);
            venues.saveVenue(current.edit(name, address, active));
            journal.audit(actor, "venue.updated", id, "Existing occurrence location snapshots are retained.",
                clock.instant());
            return null;
        });
    }
}
