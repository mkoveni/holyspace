package org.onechurch.volunteering.application.command.approvevolunteerreminder;

import java.util.UUID;

public record ApproveVolunteerReminderCommand(UUID actorId, UUID requestId, UUID assignmentId, UUID contactId,
    String message, String reviewHash, String verificationEvidence) {
    @Override
    public String toString() {
        return "ApproveVolunteerReminderCommand[requestId=" + requestId + "]";
    }
}
