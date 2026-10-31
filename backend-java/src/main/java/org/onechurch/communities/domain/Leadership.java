package org.onechurch.communities.domain;
import java.time.Instant;
import java.util.Set;
import java.util.UUID;
public record Leadership(UUID id, UUID membershipId, UUID communityId, UUID accountId, String role, Instant startedAt,
    Instant endedAt, long version) {
    public Leadership {
        if (!Set.of("LEADER", "CO_LEADER").contains(role)) {
            throw CommunityException.invalid("Choose leader or co-leader.");
        }
    }
    public Leadership end(Instant now) {
        if (endedAt != null) {
            throw CommunityException.conflict("This appointment has already ended.");
        }
        return new Leadership(id, membershipId, communityId, accountId, role, startedAt, CommunityRules.time(now),
            version + 1);
    }
}
