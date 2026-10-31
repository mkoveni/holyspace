package org.onechurch.identityaccess.application.contract;

import java.time.Instant;
import java.util.Optional;
import java.util.UUID;

public interface SessionReader {

    record Session(UUID id, UUID accountId, Instant expiresAt) {}

    Optional<Session> find(String tokenHash);

    Optional<Session> findById(UUID sessionId);
}
