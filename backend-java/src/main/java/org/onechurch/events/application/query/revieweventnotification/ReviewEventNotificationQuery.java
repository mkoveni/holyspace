package org.onechurch.events.application.query.revieweventnotification;
import java.util.UUID;
public record ReviewEventNotificationQuery(UUID registrationId, UUID contactId, String purpose, String message) {
    @Override
    public String toString() {
        return "ReviewEventNotificationQuery[redacted]";
    }
}
