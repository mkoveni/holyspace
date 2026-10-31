package org.onechurch.communities.application.contract;
import org.onechurch.communities.domain.Community;
import org.onechurch.communities.domain.GroupMeeting;
import org.onechurch.communities.domain.Leadership;
import org.onechurch.communities.domain.Membership;

import java.time.Instant;
import java.time.LocalDate;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
public interface CommunityReader {
    record Member(Membership membership, String name) {}
    record Roster(UUID id, UUID meetingId, UUID personId, UUID membershipId, boolean guest, String status, long version,
        String name) {}
    record History(UUID id, UUID targetId, String action, String reason, UUID actorId, Instant occurredAt) {}
    record FollowUp(UUID id, UUID communityId, UUID personId, String name, String note, LocalDate dueOn, String status,
        long version) {}
    record Request(String hash, UUID target) {}
    record Notice(UUID id, UUID communityId, UUID membershipId, UUID contactId, String requestHash, String reviewHash,
        UUID actorId, UUID deliveryId) {}
    record Report(UUID meetingId, long expected, long present, long absent, long excused, long unrecorded,
        long guests) {}
    Optional<Community> community(UUID id);
    List<Community> directory(String kind, String search, boolean archived, UUID scopedAccount, int limit, int offset,
        Instant at);
    long directoryCount(String kind, String search, boolean archived, UUID scopedAccount, Instant at);
    boolean leads(UUID community, UUID account, Instant at);
    Optional<Membership> membership(UUID id);
    Optional<Membership> currentMembership(UUID community, UUID person, Instant at);
    List<Member> members(UUID community, boolean ended, int limit, int offset);
    long memberCount(UUID community, boolean ended);
    List<Membership> membersAt(UUID community, Instant at, int limit);
    Optional<Leadership> leadership(UUID id);
    Optional<Leadership> activeLeadership(UUID membership);
    List<Leadership> leadership(UUID community, int limit, int offset);
    Optional<GroupMeeting> meeting(UUID id);
    List<GroupMeeting> meetings(UUID community, int limit, int offset);
    long meetingCount(UUID community);
    List<Roster> roster(UUID meeting, int limit, int offset);
    long rosterCount(UUID meeting);
    Optional<Roster> rosterRow(UUID id);
    List<History> history(UUID community, int limit, int offset);
    List<FollowUp> followups(UUID community, boolean completed, int limit, int offset);
    Optional<FollowUp> followup(UUID id);
    List<Report> reports(List<UUID> meetings);
    boolean recordedOccurrence(UUID occurrence);
    Optional<Request> request(UUID request);
    Optional<Notice> notice(UUID id);
    List<Notice> notices(UUID community, int limit, int offset);
}
