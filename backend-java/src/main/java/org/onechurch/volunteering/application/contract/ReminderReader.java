package org.onechurch.volunteering.application.contract;

import java.time.Instant;
import java.util.Optional;
import java.util.UUID;

public interface ReminderReader {
    record Reminder(UUID id, UUID assignmentId, long assignmentVersion, long shiftVersion, UUID contactId,
        String reviewHash, String evidence, UUID createdBy, Instant createdAt, UUID deliveryId) {
        @Override
        public String toString() {
            return "Reminder[id=" + id + "]";
        }
    }
    Optional<Reminder> reminder(UUID id);
}
