package org.onechurch.events.application.command.updatechurchevent;
import java.util.UUID;
public record UpdateChurchEventCommand(UUID actorId, UUID eventId, long expectedVersion, String title, String type,
    String description, boolean active, String reason) {}
