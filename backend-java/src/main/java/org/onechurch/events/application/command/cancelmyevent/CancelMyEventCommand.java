package org.onechurch.events.application.command.cancelmyevent;
import java.util.UUID;
public record CancelMyEventCommand(UUID actorId, UUID occurrenceId, long expectedVersion) {}
