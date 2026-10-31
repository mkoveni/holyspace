package org.onechurch.events.application.contract;
import org.onechurch.events.domain.EventRegistration;
import org.onechurch.events.domain.RegistrationPolicy;

import java.util.List;
import java.util.Optional;
import java.util.UUID;
public interface RegistrationReader {
    record Counts(long confirmed, long waitlisted, long cancelled) {}
    Optional<RegistrationPolicy> policy(UUID occurrenceId);
    Optional<EventRegistration> registration(UUID id);
    Optional<EventRegistration> forPerson(UUID occurrence, UUID person);
    Counts counts(UUID occurrence);
    java.util.Map<UUID, Counts> countsMany(List<UUID> occurrences);
    List<EventRegistration> registrations(UUID occurrence, String status, int limit, int offset);
    long registrationCount(UUID occurrence, String status);
}
