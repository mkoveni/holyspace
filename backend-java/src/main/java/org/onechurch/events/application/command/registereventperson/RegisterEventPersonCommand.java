package org.onechurch.events.application.command.registereventperson;
import java.util.UUID;
public record RegisterEventPersonCommand(UUID actorId, UUID requestId, UUID occurrenceId, UUID personId,
    Long expectedVersion) {}
