package org.onechurch.identityaccess.application.contract;

import java.time.Instant;
import java.util.UUID;

public interface SessionWriter {

    void create(UUID id, UUID accountId, String tokenHash, Instant expiresAt, Instant now);

    default void create(UUID id, UUID accountId, String tokenHash, Instant expiresAt, Instant now, String deviceName) {
        create(id, accountId, tokenHash, expiresAt, now);
    }
    void revoke(UUID sessionId, UUID accountId);

    void revokeAll(UUID accountId);
}
