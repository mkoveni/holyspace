package org.onechurch.childrenministry.participation.domain;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;

import java.time.Instant;
import java.util.UUID;
public record Programme(UUID id, String name, UUID currentPolicyId, int minStaff, int childrenPerStaff, long version,
    Instant createdAt) {
    public Programme {
        createdAt = ChildrenRules.instant(createdAt);
        name = ChildrenRules.text(name, 120);
        if (minStaff < 1 || childrenPerStaff < 1 || minStaff > 100 || childrenPerStaff > 100) {
            throw ChildrenException.invalid("Invalid staffing policy.");
        }
    }

}
