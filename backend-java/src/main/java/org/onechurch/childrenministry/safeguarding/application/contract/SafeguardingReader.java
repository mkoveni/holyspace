package org.onechurch.childrenministry.safeguarding.application.contract;

import org.onechurch.childrenministry.safeguarding.domain.Consent;
import org.onechurch.childrenministry.safeguarding.domain.GuardianAuthority;
import org.onechurch.childrenministry.safeguarding.domain.PickupAuthorization;

import java.time.Instant;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
public interface SafeguardingReader {
    Optional<GuardianAuthority> guardianAuthority(UUID id);
    Optional<Consent> consent(UUID id);
    Optional<PickupAuthorization> pickupAuthorization(UUID id);
    List<GuardianAuthority> authorities(UUID childId);
    List<Consent> consents(UUID childId);
    List<PickupAuthorization> authorizations(UUID childId);
    List<Consent> validConsents(UUID programmeId, UUID policyId, UUID childId, Instant from, Instant until);
    List<PickupAuthorization> validAuthorizations(UUID programmeId, UUID childId, UUID collectorId, Instant at);
}
