package org.onechurch.events.application.contract;
import java.time.Instant;
import java.util.Optional;
import java.util.UUID;
public interface EventNotificationReader {
    record Notification(UUID id, UUID registrationId, long registrationVersion, long scheduleRevision,
        long eventVersion, String purpose, UUID contactId, String reviewHash, String requestHash, String evidence,
        UUID createdBy, Instant createdAt, UUID deliveryId) {
        @Override
        public String toString() {
            return "Notification[id=" + id + "]";
        }
    }
    Optional<Notification> notification(UUID id);
}
