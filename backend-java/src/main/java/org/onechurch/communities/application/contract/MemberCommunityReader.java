package org.onechurch.communities.application.contract;
import java.time.Instant;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
public interface MemberCommunityReader {
    record MemberCommunity(UUID id, String kind, String name, String description, String campus, String parentName,
        Instant joinedAt, String role) {}
    record Leader(UUID personId, String role) {}
    record Meeting(UUID id, UUID communityId, String communityName, UUID occurrenceId, String status,
        String attendance) {}
    List<MemberCommunity> communities(UUID person, String kind, Instant at, int limit, int offset);
    Optional<MemberCommunity> community(UUID person, UUID id, Instant at);
    List<Leader> leaders(UUID person, UUID community, Instant at, int limit, int offset);
    List<Meeting> meetings(UUID person, UUID community, Instant from, Instant until, int limit, int offset);
    Optional<Meeting> meeting(UUID person, UUID id, Instant at);
}
