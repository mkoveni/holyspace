package org.onechurch.events.application.contract;
import org.onechurch.events.domain.OccurrenceCloseout;

import java.time.Instant;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
public interface CloseoutReader {
    record Notice(UUID id, UUID registrationId, String purpose, long registrationVersion, long scheduleRevision,
        long eventVersion) {}
    record History(UUID id, long version, String action, String note, String followUp, long attended, long absent,
        long waitlisted, long unresolvedNotices, UUID actorId, String actorName, Instant occurredAt) {}
    Optional<OccurrenceCloseout> closeout(UUID occurrence);
    List<History> history(UUID occurrence, int limit, int offset);
    List<Notice> notices(UUID occurrence, int limit);
    long currentReminderBookings(UUID occurrence);
    long attendanceRevision(UUID occurrence);
}
