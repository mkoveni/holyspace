package org.onechurch.events.application.command.configureeventregistration;
import java.util.UUID;
public record ConfigureEventRegistrationCommand(UUID actorId, UUID occurrenceId, long expectedVersion, boolean open,
    Integer capacity, boolean waitlistEnabled, String reason) {}
