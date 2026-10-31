package org.onechurch.volunteering.domain;

import java.time.Duration;
import java.time.Instant;
import java.util.Set;
import java.util.UUID;

public record Shift(UUID id, UUID requestId, String requestHash, UUID teamId, UUID roleId, String sourceKind,
    UUID sourceId, String sourceRevision, Instant startsAt, Instant endsAt, int positions, String status, long version,
    long scheduleRevision) {
    public Shift {
        ServingRules.id(id);
        ServingRules.id(requestId);
        ServingRules.id(teamId);
        ServingRules.id(roleId);
        startsAt = ServingRules.instant(startsAt);
        endsAt = ServingRules.instant(endsAt);
        ServingRules.period(startsAt, endsAt);
        if (Duration.between(startsAt, endsAt).compareTo(Duration.ofDays(7)) > 0 || positions < 1 || positions > 100
            || !Set.of("OPEN", "CANCELLED").contains(status) || sourceKind == null || sourceKind.length() > 40
            || (sourceKind.equals("STANDALONE") != (sourceId == null))) {
            throw VolunteerException.invalid("Choose a source, at most seven days and 1–100 positions.");
        }
    }
    public Shift change(Instant from, Instant until, int positions, String status) {
        if (this.status.equals("CANCELLED")) {
            throw VolunteerException.conflict("Cancelled shifts cannot reopen.");
        }
        return new Shift(id, requestId, requestHash, teamId, roleId, sourceKind, sourceId, sourceRevision, from, until,
            positions, status, version + 1, scheduleRevision + 1);
    }
    public Shift sourceRevision(String revision) {
        return new Shift(id, requestId, requestHash, teamId, roleId, sourceKind, sourceId, revision, startsAt, endsAt,
            positions, status, version, scheduleRevision);
    }
    public Shift touch() {
        return new Shift(id, requestId, requestHash, teamId, roleId, sourceKind, sourceId, sourceRevision, startsAt,
            endsAt, positions, status, version + 1, scheduleRevision);
    }
}
