package org.onechurch.events.application.command.createeventvenue;
import java.util.UUID;
public record CreateEventVenueCommand(UUID actorId, String name, String address, boolean exclusive) {}
