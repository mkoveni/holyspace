package org.onechurch.events.application.command.registereventwalkin;
import java.util.UUID;
public record RegisterEventWalkInCommand(UUID actorId, UUID requestId, UUID occurrenceId, UUID personId,
    Long expectedVersion) {}
