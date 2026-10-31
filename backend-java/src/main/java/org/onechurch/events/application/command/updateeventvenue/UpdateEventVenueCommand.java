package org.onechurch.events.application.command.updateeventvenue;
import java.util.UUID;
public record UpdateEventVenueCommand(UUID actorId, UUID venueId, long expectedVersion, String name, String address,
    boolean active) {}
