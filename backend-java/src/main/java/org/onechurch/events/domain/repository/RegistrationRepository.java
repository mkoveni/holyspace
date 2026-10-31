package org.onechurch.events.domain.repository;
import org.onechurch.events.domain.EventRegistration;
import org.onechurch.events.domain.RegistrationPolicy;

import java.time.Instant;
import java.util.UUID;
public interface RegistrationRepository {
    void insertPolicy(RegistrationPolicy policy);
    void savePolicy(RegistrationPolicy policy);
    void insertRegistration(EventRegistration registration);
    void saveRegistration(EventRegistration registration);
    void cancelOccurrenceRegistrations(UUID occurrence, UUID actor, String reason, Instant now);
    void promote(UUID occurrence, int places, UUID actor, Instant now);
}
