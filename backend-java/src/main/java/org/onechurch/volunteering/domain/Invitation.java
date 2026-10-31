package org.onechurch.volunteering.domain;

import java.time.Instant;
import java.util.Set;
import java.util.UUID;

public record Invitation(UUID id, UUID assignmentId, long assignmentVersion, long shiftVersion, Instant expiresAt,
    String status, long version, Instant respondedAt) {
    public Invitation {
        expiresAt = ServingRules.instant(expiresAt);
    }
    public Invitation respond(String next, Instant at) {
        if (!status.equals("PENDING") || !Set.of("ACCEPTED", "DECLINED", "EXPIRED", "WITHDRAWN").contains(next)) {
            throw VolunteerException.conflict("This invitation is no longer pending.");
        }
        return new Invitation(id, assignmentId, assignmentVersion, shiftVersion, expiresAt, next, version + 1, at);
    }
}
