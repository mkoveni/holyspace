package org.onechurch.childrenministry.safeguarding.domain;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;

import java.time.Instant;
import java.util.UUID;
public record Consent(UUID id, UUID programmeId, UUID policyId, UUID childId, UUID authorityId, Instant validFrom,
    Instant validUntil, Instant acceptedAt, UUID recordedBy, String evidenceReference, Instant revokedAt,
    String revocationReason, long version) {
    public Consent {
        validFrom = ChildrenRules.instant(validFrom);
        validUntil = ChildrenRules.instant(validUntil);
        acceptedAt = ChildrenRules.instant(acceptedAt);
        revokedAt = ChildrenRules.instant(revokedAt);
        ChildrenRules.period(validFrom, validUntil);
        evidenceReference = ChildrenRules.text(evidenceReference, 255);
    }

    public boolean covers(Instant from, Instant until) {
        return revokedAt == null && ChildrenRules.covers(validFrom, validUntil, from, until);
    }
    public Consent revoke(long expected, String reason, Instant now) {
        ChildrenRules.version(version, expected);
        if (revokedAt != null) {
            throw ChildrenException.conflict("This record is already revoked.");
        }
        return new Consent(id, programmeId, policyId, childId, authorityId, validFrom, validUntil, acceptedAt,
            recordedBy, evidenceReference, now, ChildrenRules.text(reason, 500), version + 1);
    }
}
