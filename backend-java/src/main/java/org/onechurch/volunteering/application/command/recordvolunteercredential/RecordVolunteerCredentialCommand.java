package org.onechurch.volunteering.application.command.recordvolunteercredential;

import java.time.Instant;
import java.util.UUID;

public record RecordVolunteerCredentialCommand(UUID actorId, UUID volunteerId, long expectedVersion, UUID typeId,
    Instant validFrom, Instant validUntil, String evidence) {}
