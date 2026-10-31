package org.onechurch.events.application.command.configurememberevent;
import java.util.UUID;
public record ConfigureMemberEventCommand(UUID actorId, UUID eventId, boolean enabled, long expectedVersion) {}
