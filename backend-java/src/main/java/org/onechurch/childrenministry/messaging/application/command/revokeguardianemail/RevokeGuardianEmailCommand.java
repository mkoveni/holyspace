package org.onechurch.childrenministry.messaging.application.command.revokeguardianemail;
import java.util.UUID;
public record RevokeGuardianEmailCommand(UUID actorId, UUID verificationId, String reason) {}
