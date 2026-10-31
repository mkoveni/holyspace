package org.onechurch.communities.domain;
import java.time.Instant;
import java.util.UUID;
public record GroupMeeting(UUID id, UUID communityId, UUID occurrenceId, Instant scheduledAt, String status,
    boolean rosterFrozen, long version) {
    public GroupMeeting change(String next, boolean frozen) {
        return new GroupMeeting(id, communityId, occurrenceId, scheduledAt, next, frozen, version + 1);
    }
}
