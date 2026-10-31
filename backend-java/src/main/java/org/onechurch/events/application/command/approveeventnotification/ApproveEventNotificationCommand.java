package org.onechurch.events.application.command.approveeventnotification;
import java.util.UUID;
public record ApproveEventNotificationCommand(UUID actorId, UUID requestId, UUID registrationId, UUID contactId,
    String purpose, String message, String reviewHash, String verificationEvidence) {
    @Override
    public String toString() {
        return "ApproveEventNotificationCommand[redacted]";
    }
}
