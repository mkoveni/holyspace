package org.onechurch.events.application.command.checkineventperson;
import java.util.UUID;
public record CheckInEventPersonCommand(UUID actorId, UUID requestId, UUID registrationId, long expectedVersion) {}
