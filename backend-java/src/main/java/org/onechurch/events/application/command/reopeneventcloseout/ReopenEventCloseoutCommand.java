package org.onechurch.events.application.command.reopeneventcloseout;
import java.util.UUID;
public record ReopenEventCloseoutCommand(UUID actorId, UUID occurrenceId, long expectedVersion, String reason) {}
