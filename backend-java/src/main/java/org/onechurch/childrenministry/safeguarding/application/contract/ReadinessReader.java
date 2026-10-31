package org.onechurch.childrenministry.safeguarding.application.contract;
import org.onechurch.childrenministry.participation.domain.ClassAssignment;
import org.onechurch.childrenministry.participation.domain.Enrolment;
import org.onechurch.childrenministry.safeguarding.domain.Consent;
import org.onechurch.childrenministry.safeguarding.domain.GuardianAuthority;
import org.onechurch.childrenministry.safeguarding.domain.PickupAuthorization;

import java.time.Instant;
import java.time.LocalDate;
import java.util.Collection;
import java.util.List;
import java.util.UUID;
public interface ReadinessReader {
    record Facts(List<Enrolment> enrolments, List<ClassAssignment> assignments, List<GuardianAuthority> guardians,
        List<Consent> consents, List<PickupAuthorization> collectors) {}
    Facts load(UUID programmeId, UUID policyId, Collection<UUID> childIds, LocalDate on, Instant at);
}
