package org.onechurch.volunteering.domain.repository;

import org.onechurch.volunteering.domain.AvailabilityException;
import org.onechurch.volunteering.domain.AvailabilityRule;
import org.onechurch.volunteering.domain.Credential;
import org.onechurch.volunteering.domain.CredentialType;
import org.onechurch.volunteering.domain.Volunteer;

import java.time.Instant;
import java.util.Optional;
import java.util.UUID;

public interface VolunteerRepository {
    Optional<Volunteer> lock(UUID id);
    void insert(Volunteer volunteer);
    void save(Volunteer volunteer);
    void addCredentialType(CredentialType type);
    void addCredential(Credential credential);
    void revokeCredential(UUID id, Instant at);
    void availability(AvailabilityRule rule);
    void exception(AvailabilityException exception);
}
