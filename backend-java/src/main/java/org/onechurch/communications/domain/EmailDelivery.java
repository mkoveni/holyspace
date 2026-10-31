package org.onechurch.communications.domain;

import java.time.Instant;
import java.util.UUID;

public record EmailDelivery(UUID id, UUID communicationId, UUID verificationId, UUID contactId, String recipient,
    String subject, String body, String reviewHash, UUID approvedBy, Instant approvedAt, Instant scheduledAt,
    String status, int attempts, String messageId, UUID attemptId, Instant startedAt, Instant finishedAt,
    String errorCode, boolean captured, long version, String contentType, String templateKey, Long templateVersion) {
    public EmailDelivery(UUID id, UUID communicationId, UUID verificationId, UUID contactId, String recipient,
        String subject, String body, String reviewHash, UUID approvedBy, Instant approvedAt, Instant scheduledAt,
        String status, int attempts, String messageId, UUID attemptId, Instant startedAt, Instant finishedAt,
        String errorCode, boolean captured, long version) {
        this(id, communicationId, verificationId, contactId, recipient, subject, body, reviewHash, approvedBy,
            approvedAt, scheduledAt, status, attempts, messageId, attemptId, startedAt, finishedAt, errorCode, captured,
            version, "text/plain", null, null);
    }

    public boolean waiting() {
        return status.equals("QUEUED") || status.equals("SCHEDULED");
    }
    public EmailDelivery transition(String next, Instant scheduled, Instant now, String error, boolean capture) {
        return new EmailDelivery(id, communicationId, verificationId, contactId, recipient, subject, body, reviewHash,
            approvedBy, approvedAt, scheduled, next, attempts, messageId, attemptId, startedAt, now, error, capture,
            version + 1, contentType, templateKey, templateVersion);
    }
    public EmailDelivery claim(Instant now) {
        if (!waiting() || scheduledAt.isAfter(now)) {
            throw CommunicationException.conflict("This delivery is not due.");
        }
        return new EmailDelivery(id, communicationId, verificationId, contactId, recipient, subject, body, reviewHash,
            approvedBy, approvedAt, scheduledAt, "SENDING", attempts + 1, messageId, UUID.randomUUID(), now, null, "",
            false, version + 1, contentType, templateKey, templateVersion);
    }
    @Override
    public String toString() {
        return "EmailDelivery[id=" + id + ", status=" + status + "]";
    }
    public EmailDelivery approveRetry(UUID actor, Instant now) {
        return new EmailDelivery(id, communicationId, verificationId, contactId, recipient, subject, body, reviewHash,
            actor, now, now, "QUEUED", attempts, messageId, attemptId, startedAt, now, "", false, version + 1,
            contentType, templateKey, templateVersion);
    }

}
