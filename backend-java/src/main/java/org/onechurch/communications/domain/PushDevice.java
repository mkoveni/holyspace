package org.onechurch.communications.domain;
import java.time.Instant;
import java.util.UUID;
public record PushDevice(UUID id, UUID accountId, UUID sessionId, String secretHash, String tokenHash,
    String encryptedToken, UUID projectId, String platform, String status, long revision, Instant expiresAt,
    Instant updatedAt) {
    public PushDevice {
        java.util.Objects.requireNonNull(id);
        java.util.Objects.requireNonNull(accountId);
        java.util.Objects.requireNonNull(sessionId);
        java.util.Objects.requireNonNull(projectId);
        java.util.Objects.requireNonNull(expiresAt);
        java.util.Objects.requireNonNull(updatedAt);
        if (secretHash == null || !secretHash.matches("[a-f0-9]{64}") || tokenHash == null
            || !tokenHash.matches("[a-f0-9]{64}") || encryptedToken == null || encryptedToken.isBlank() || revision < 0
            || !java.util.Set.of("android", "ios").contains(platform)
            || !java.util.Set.of("ACTIVE", "REVOKED", "INVALID").contains(status)) {
            throw CommunicationException.invalid("Invalid push registration.");
        }
    }
    public boolean active(Instant at) {
        return status.equals("ACTIVE") && expiresAt.isAfter(at);
    }
    public PushDevice revoke(String state, Instant at) {
        return new PushDevice(id, accountId, sessionId, secretHash, tokenHash, encryptedToken, projectId, platform,
            state, revision + 1, expiresAt, at);
    }
    @Override
    public String toString() {
        return "PushDevice[id=" + id + ", status=" + status + "]";
    }
}
