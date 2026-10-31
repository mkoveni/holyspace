package org.onechurch.communities.domain;
import java.time.Instant;
import java.util.UUID;
public record Membership(UUID id, UUID communityId, UUID personId, Instant startedAt, Instant endedAt, long version) {
    public boolean covers(Instant at) {
        return !at.isBefore(startedAt) && (endedAt == null || at.isBefore(endedAt));
    }
    public Membership end(Instant at) {
        if (!covers(at)) {
            throw CommunityException.conflict("This membership has already ended.");
        }
        return new Membership(id, communityId, personId, startedAt, CommunityRules.time(at), version + 1);
    }
}
