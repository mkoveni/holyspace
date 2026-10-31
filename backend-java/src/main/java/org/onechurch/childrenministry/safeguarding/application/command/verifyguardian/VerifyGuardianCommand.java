package org.onechurch.childrenministry.safeguarding.application.command.verifyguardian;

import org.onechurch.childrenministry.shared.domain.ChildrenRules;

import java.time.Instant;
import java.util.UUID;
public record VerifyGuardianCommand(UUID actorId, UUID childId, UUID guardianId, Instant validFrom, Instant validUntil,
    String evidenceReference) {
    public VerifyGuardianCommand {
        validFrom = ChildrenRules.instant(validFrom);
        validUntil = ChildrenRules.instant(validUntil);
    }
}
