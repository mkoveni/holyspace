package org.onechurch.communities.application.contract;
import java.time.Instant;
import java.util.List;
import java.util.UUID;
public interface CommunityParticipation {
    record Entry(UUID communityId, String kind, String name, UUID membershipId, Instant startedAt, Instant endedAt) {}
    List<Entry> forPerson(UUID person, int limit, int offset);
}
