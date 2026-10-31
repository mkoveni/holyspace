package org.onechurch.childrenministry.safeguarding.domain.repository;

import org.onechurch.childrenministry.safeguarding.domain.Consent;
import org.onechurch.childrenministry.safeguarding.domain.GuardianAuthority;
import org.onechurch.childrenministry.safeguarding.domain.PickupAuthorization;

import java.util.Optional;
import java.util.UUID;
public interface SafeguardingRepository {
    Optional<GuardianAuthority> guardianAuthorityForUpdate(UUID id);
    void insertGuardianAuthority(GuardianAuthority value);
    void updateGuardianAuthority(GuardianAuthority value);
    Optional<Consent> consentForUpdate(UUID id);
    void insertConsent(Consent value);
    void updateConsent(Consent value);
    Optional<PickupAuthorization> pickupAuthorizationForUpdate(UUID id);
    void insertPickupAuthorization(PickupAuthorization value);
    void updatePickupAuthorization(PickupAuthorization value);

}
