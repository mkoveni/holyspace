package org.onechurch.childrenministry.safeguarding.domain;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;

import java.time.Instant;
import java.util.UUID;
public record PickupAuthorization(UUID id, UUID programmeId, UUID childId, UUID collectorId, UUID authorityId,
    Instant validFrom, Instant validUntil, String evidenceReference, UUID recordedBy, Instant revokedAt,
    String revocationReason, long version) {
    public PickupAuthorization {
        validFrom = ChildrenRules.instant(validFrom);
        validUntil = ChildrenRules.instant(validUntil);
        revokedAt = ChildrenRules.instant(revokedAt);
        ChildrenRules.period(validFrom, validUntil);
        evidenceReference = ChildrenRules.text(evidenceReference, 255);
        if (childId.equals(collectorId)) {
            throw ChildrenException.invalid("A child cannot collect themselves.");
        }
    }

    public boolean covers(Instant from, Instant until) {
        return revokedAt == null && ChildrenRules.covers(validFrom, validUntil, from, until);
    }
    public PickupAuthorization revoke(long expected, String reason, Instant now) {
        ChildrenRules.version(version, expected);
        if (revokedAt != null) {
            throw ChildrenException.conflict("This record is already revoked.");
        }
        return new PickupAuthorization(id, programmeId, childId, collectorId, authorityId, validFrom, validUntil,
            evidenceReference, recordedBy, now, ChildrenRules.text(reason, 500), version + 1);
    }
}
