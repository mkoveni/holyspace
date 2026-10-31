package org.onechurch.childrenministry.safeguarding.application.command.authorizepickup;

import org.onechurch.childrenministry.shared.domain.ChildrenRules;

import java.time.Instant;
import java.util.UUID;
public record AuthorizePickupCommand(UUID actorId, UUID programmeId, UUID childId, UUID collectorId, UUID authorityId,
    Instant validFrom, Instant validUntil, String evidenceReference) {
    public AuthorizePickupCommand {
        validFrom = ChildrenRules.instant(validFrom);
        validUntil = ChildrenRules.instant(validUntil);
    }
}
