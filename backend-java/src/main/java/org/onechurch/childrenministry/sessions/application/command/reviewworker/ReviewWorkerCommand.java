package org.onechurch.childrenministry.sessions.application.command.reviewworker;

import org.onechurch.childrenministry.shared.domain.ChildrenRules;

import java.time.Instant;
import java.util.UUID;
public record ReviewWorkerCommand(UUID actorId, UUID personId, long expectedVersion, Instant screenedUntil,
    Instant trainedUntil, String evidenceReference, boolean revoked) {
    public ReviewWorkerCommand {
        screenedUntil = ChildrenRules.instant(screenedUntil);
        trainedUntil = ChildrenRules.instant(trainedUntil);
    }
}
