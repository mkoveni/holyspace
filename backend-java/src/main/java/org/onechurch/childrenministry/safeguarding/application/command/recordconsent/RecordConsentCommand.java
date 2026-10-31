package org.onechurch.childrenministry.safeguarding.application.command.recordconsent;

import org.onechurch.childrenministry.shared.domain.ChildrenRules;

import java.time.Instant;
import java.util.UUID;
public record RecordConsentCommand(UUID actorId, UUID childId, UUID programmeId, UUID policyId, UUID authorityId,
    Instant validFrom, Instant validUntil, Instant acceptedAt, String evidenceReference) {
    public RecordConsentCommand {
        validFrom = ChildrenRules.instant(validFrom);
        validUntil = ChildrenRules.instant(validUntil);
        acceptedAt = ChildrenRules.instant(acceptedAt);
    }
}
