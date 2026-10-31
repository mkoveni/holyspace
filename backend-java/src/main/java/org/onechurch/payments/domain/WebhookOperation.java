package org.onechurch.payments.domain;
import java.time.Instant;
import java.util.UUID;
public record WebhookOperation(UUID id, UUID revisionId, String mode, String kind, String targetId, String backendUrl,
    String requestHash, UUID actorId, String status, String webhookId, String encryptedSecret, Instant createdAt) {
    @Override
    public String toString() {
        return "WebhookOperation[id=" + id + ", status=" + status + "]";
    }
}
