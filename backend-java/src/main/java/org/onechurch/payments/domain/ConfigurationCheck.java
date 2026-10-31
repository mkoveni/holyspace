package org.onechurch.payments.domain;
import java.time.Instant;
import java.util.UUID;
public record ConfigurationCheck(UUID id, UUID revisionId, boolean successful, String message, UUID checkedBy,
    Instant checkedAt) {}
