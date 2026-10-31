package org.onechurch.childrenministry.sessions.domain;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;

import java.time.Instant;
import java.util.UUID;
public record Worker(UUID personId, UUID accountId, Instant verifiedFrom, Instant screenedUntil, Instant trainedUntil,
    String evidenceReference, UUID verifiedBy, Instant revokedAt, long version) {
    public Worker {
        verifiedFrom = ChildrenRules.instant(verifiedFrom);
        screenedUntil = ChildrenRules.instant(screenedUntil);
        trainedUntil = ChildrenRules.instant(trainedUntil);
        revokedAt = ChildrenRules.instant(revokedAt);
        ChildrenRules.period(verifiedFrom, screenedUntil);
        ChildrenRules.period(verifiedFrom, trainedUntil);
        evidenceReference = ChildrenRules.text(evidenceReference, 255);
    }
    public boolean eligible(Instant now, Instant through) {
        return revokedAt == null && !verifiedFrom.isAfter(now) && !screenedUntil.isBefore(through)
            && !trainedUntil.isBefore(through);
    }
}
