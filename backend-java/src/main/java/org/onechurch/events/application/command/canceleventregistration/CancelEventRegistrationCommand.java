package org.onechurch.events.application.command.canceleventregistration;
import java.util.UUID;
public record CancelEventRegistrationCommand(UUID actorId, UUID registrationId, long expectedVersion, String reason) {}
