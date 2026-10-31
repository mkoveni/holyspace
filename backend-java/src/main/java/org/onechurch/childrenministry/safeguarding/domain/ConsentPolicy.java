package org.onechurch.childrenministry.safeguarding.domain;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;

import java.time.Instant;
import java.util.UUID;
public record ConsentPolicy(UUID id, UUID programmeId, String label, String documentReference, String contentHash,
    Instant createdAt) {
    public ConsentPolicy {
        createdAt = ChildrenRules.instant(createdAt);
        label = ChildrenRules.text(label, 40);
        documentReference = ChildrenRules.text(documentReference, 255);
        if (contentHash == null || !contentHash.matches("[a-f0-9]{64}")) {
            throw ChildrenException.invalid("Supply the policy document SHA-256 hash.");
        }
    }

}
