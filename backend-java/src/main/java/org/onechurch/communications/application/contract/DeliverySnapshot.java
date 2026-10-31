package org.onechurch.communications.application.contract;
import java.time.Instant;
import java.util.UUID;
public record DeliverySnapshot(UUID id, UUID communicationId, UUID verificationId, UUID contactId, String recipient,
    String subject, String body, String reviewHash, UUID approvedBy, Instant approvedAt, Instant scheduledAt,
    String status, int attempts, String messageId, UUID attemptId, Instant startedAt, Instant finishedAt,
    String errorCode, boolean captured, long version, String contentType, String templateKey, Long templateVersion) {
    public DeliverySnapshot(UUID id, UUID communicationId, UUID verificationId, UUID contactId, String recipient,
        String subject, String body, String reviewHash, UUID approvedBy, Instant approvedAt, Instant scheduledAt,
        String status, int attempts, String messageId, UUID attemptId, Instant startedAt, Instant finishedAt,
        String errorCode, boolean captured, long version) {
        this(id, communicationId, verificationId, contactId, recipient, subject, body, reviewHash, approvedBy,
            approvedAt, scheduledAt, status, attempts, messageId, attemptId, startedAt, finishedAt, errorCode, captured,
            version, "text/plain", null, null);
    }

    @Override
    public String toString() {
        return "DeliverySnapshot[id=" + id + ", status=" + status + "]";
    }
}
