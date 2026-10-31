package org.onechurch.events.domain;
import java.util.UUID;
public record RegistrationPolicy(UUID occurrenceId, boolean open, Integer capacity, boolean waitlistEnabled,
    long nextPosition, long version) {
    public RegistrationPolicy {
        EventRules.id(occurrenceId);
        if (capacity != null && (capacity < 1 || capacity > 10000)) {
            throw EventException.invalid("Choose a capacity of 1–10,000, or leave it unlimited.");
        }
    }
    public RegistrationPolicy configure(boolean open, Integer capacity, boolean waitlist) {
        return new RegistrationPolicy(occurrenceId, open, capacity, waitlist, nextPosition, version + 1);
    }
    public RegistrationPolicy allocate() {
        return new RegistrationPolicy(occurrenceId, open, capacity, waitlistEnabled, nextPosition + 1, version + 1);
    }
    public RegistrationPolicy touch() {
        return configure(open, capacity, waitlistEnabled);
    }
}
