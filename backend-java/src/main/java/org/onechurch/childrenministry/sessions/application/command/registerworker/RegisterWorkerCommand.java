package org.onechurch.childrenministry.sessions.application.command.registerworker;

import org.onechurch.childrenministry.shared.domain.ChildrenRules;

import java.time.Instant;
import java.util.UUID;
public record RegisterWorkerCommand(UUID actorId, UUID personId, UUID accountId, Instant screenedUntil,
    Instant trainedUntil, String evidenceReference) {
    public RegisterWorkerCommand {
        screenedUntil = ChildrenRules.instant(screenedUntil);
        trainedUntil = ChildrenRules.instant(trainedUntil);
    }
}
