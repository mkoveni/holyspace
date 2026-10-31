package org.onechurch.childrenministry.messaging.application.command.queueguardianemail;
import java.time.Instant;
import java.util.UUID;
public record QueueGuardianEmailCommand(UUID actorId, UUID communicationId, long expectedVersion, UUID verificationId,
    String reviewHash, Instant scheduledAt) {}
