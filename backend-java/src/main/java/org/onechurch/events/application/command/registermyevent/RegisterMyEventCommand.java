package org.onechurch.events.application.command.registermyevent;
import java.util.UUID;
public record RegisterMyEventCommand(UUID actorId, UUID requestId, UUID occurrenceId, Long expectedVersion) {}
