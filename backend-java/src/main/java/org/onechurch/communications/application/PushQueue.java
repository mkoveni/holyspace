package org.onechurch.communications.application;
import org.onechurch.communications.domain.PushDevice;

import java.time.Instant;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
public interface PushQueue {
    record Job(UUID id, UUID notificationId, UUID deviceId, long deviceRevision, String topic, long preferenceVersion,
        String status, int attempts, Instant createdAt, UUID claimId, String ticketId) {}
    List<UUID> due(Instant at);
    Optional<Job> claim(UUID id, Instant at);
    Optional<PushDevice> eligible(Job job, Instant at);
    void finish(Job job, String state, Instant due, String ticket, String error, Instant at);
    void recover(Instant at);
}
