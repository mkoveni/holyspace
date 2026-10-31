package org.onechurch.events.domain;
import java.time.Instant;
import java.util.UUID;
public record AdmissionPass(String tokenHash, UUID registrationId, UUID accountId, UUID sessionId, long memberVersion,
    long registrationVersion, long scheduleRevision, Instant expiresAt, Instant usedAt) {}
