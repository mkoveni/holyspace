package org.onechurch.events.domain;
import java.time.Instant;
import java.util.Set;
import java.util.UUID;
public record EventRegistration(UUID id, UUID occurrenceId, UUID personId, String status, long position, long version,
    Instant createdAt, Instant updatedAt) {
    public EventRegistration {
        EventRules.id(id);
        EventRules.id(occurrenceId);
        EventRules.id(personId);
        createdAt = EventRules.instant(createdAt);
        updatedAt = EventRules.instant(updatedAt);
        if (status == null || !Set.of("CONFIRMED", "WAITLISTED", "CANCELLED").contains(status) || position < 1) {
            throw EventException.invalid("Invalid registration state.");
        }
    }
    public EventRegistration cancel(Instant now) {
        return new EventRegistration(id, occurrenceId, personId, "CANCELLED", position, version + 1, createdAt, now);
    }
    public EventRegistration rejoin(String state, long position, Instant now) {
        if (!status.equals("CANCELLED")) {
            throw EventException.conflict("The person already has an active registration.");
        }
        return new EventRegistration(id, occurrenceId, personId, state, position, version + 1, createdAt, now);
    }
}
