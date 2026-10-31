package org.onechurch.childrenministry.safeguarding.application;

import org.onechurch.childrenministry.safeguarding.domain.Consent;
import org.onechurch.childrenministry.safeguarding.domain.GuardianAuthority;
import org.onechurch.childrenministry.safeguarding.domain.PickupAuthorization;

import java.util.List;
public record SafeguardingView(List<GuardianAuthority> authorities, List<Consent> consents,
    List<PickupAuthorization> pickupAuthorizations) {}
