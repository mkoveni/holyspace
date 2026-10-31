package org.onechurch.childrenministry.sessions.application.command.createsession;

import org.onechurch.childrenministry.shared.domain.ChildrenRules;

import java.time.Instant;
import java.util.UUID;
public record CreateSessionCommand(UUID actorId, UUID classId, UUID roomId, Instant startsAt, Instant endsAt,
    int seatLimit) {
    public CreateSessionCommand {
        startsAt = ChildrenRules.instant(startsAt);
        endsAt = ChildrenRules.instant(endsAt);
    }
}
