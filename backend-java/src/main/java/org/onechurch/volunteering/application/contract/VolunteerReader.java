package org.onechurch.volunteering.application.contract;

import org.onechurch.volunteering.domain.AvailabilityException;
import org.onechurch.volunteering.domain.AvailabilityRule;
import org.onechurch.volunteering.domain.Credential;
import org.onechurch.volunteering.domain.CredentialType;
import org.onechurch.volunteering.domain.Volunteer;

import java.util.List;
import java.util.Optional;
import java.util.UUID;

public interface VolunteerReader {
    Optional<Volunteer> volunteer(UUID id);
    Optional<Volunteer> byPerson(UUID person);
    Optional<Volunteer> byAccount(UUID account);
    List<Volunteer> volunteers(String search, int limit, int offset);
    long volunteerCount(String search);
    List<CredentialType> credentialTypes();
    List<Credential> credentials(UUID volunteer);
    List<AvailabilityRule> availability(UUID volunteer, long revision);
    List<AvailabilityException> exceptions(UUID volunteer, long revision);
}
