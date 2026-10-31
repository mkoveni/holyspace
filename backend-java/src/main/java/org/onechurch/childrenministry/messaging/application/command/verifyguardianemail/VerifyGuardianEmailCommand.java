package org.onechurch.childrenministry.messaging.application.command.verifyguardianemail;
import java.time.Instant;
import java.util.UUID;
public record VerifyGuardianEmailCommand(UUID actorId, UUID communicationId, UUID contactId, String evidence,
    Instant validUntil) {}
