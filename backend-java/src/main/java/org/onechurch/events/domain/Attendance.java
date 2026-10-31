package org.onechurch.events.domain;
import java.time.Instant;
import java.util.Set;
import java.util.UUID;
public record Attendance(UUID id, UUID registrationId, Instant arrivedAt, String status, long version) {
    public Attendance {
        EventRules.id(id);
        EventRules.id(registrationId);
        arrivedAt = EventRules.instant(arrivedAt);
        if (status == null || !Set.of("PRESENT", "VOIDED").contains(status)) {
            throw EventException.invalid("Choose present or voided attendance.");
        }
    }
    public Attendance correct(String state) {
        return new Attendance(id, registrationId, arrivedAt, state, version + 1);
    }
}
