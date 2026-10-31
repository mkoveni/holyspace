package org.onechurch.volunteering.application.command.revokevolunteercredential;

import java.util.UUID;

public record RevokeVolunteerCredentialCommand(UUID actorId, UUID volunteerId, long expectedVersion, UUID credentialId,
    String reason) {}
