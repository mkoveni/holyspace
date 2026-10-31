package org.onechurch.childrenministry.safeguarding.domain;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;

import java.time.Instant;
import java.util.UUID;
public record GuardianAuthority(UUID id, UUID childId, UUID guardianId, Instant validFrom, Instant validUntil,
    String evidenceReference, UUID verifiedBy, Instant revokedAt, String revocationReason, long version) {
    public GuardianAuthority {
        validFrom = ChildrenRules.instant(validFrom);
        validUntil = ChildrenRules.instant(validUntil);
        revokedAt = ChildrenRules.instant(revokedAt);
        ChildrenRules.period(validFrom, validUntil);
        evidenceReference = ChildrenRules.text(evidenceReference, 255);
        if (childId.equals(guardianId)) {
            throw ChildrenException.invalid("Child and guardian must be different people.");
        }
    }

    public boolean covers(Instant from, Instant until) {
        return revokedAt == null && ChildrenRules.covers(validFrom, validUntil, from, until);
    }
    public GuardianAuthority revoke(long expected, String reason, Instant now) {
        ChildrenRules.version(version, expected);
        if (revokedAt != null) {
            throw ChildrenException.conflict("This record is already revoked.");
        }
        return new GuardianAuthority(id, childId, guardianId, validFrom, validUntil, evidenceReference, verifiedBy, now,
            ChildrenRules.text(reason, 500), version + 1);
    }
}
