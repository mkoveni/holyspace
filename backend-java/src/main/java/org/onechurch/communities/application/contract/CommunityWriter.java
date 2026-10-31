package org.onechurch.communities.application.contract;
import java.time.Instant;
import java.time.LocalDate;
import java.util.UUID;
public interface CommunityWriter {
    void scheduleChanged(UUID occurrence, Instant startsAt);
    void audit(UUID community, UUID target, String action, String reason, UUID actor, Instant at);
    void request(UUID id, String hash, UUID target);
    void roster(UUID meeting, UUID person, UUID membership, boolean guest);
    void attendance(UUID row, String status);
    void followup(UUID id, UUID community, UUID person, String note, LocalDate due, UUID actor, Instant at);
    void completeFollowup(UUID id);
    void notice(CommunityReader.Notice notice, String evidence, Instant at);
}
