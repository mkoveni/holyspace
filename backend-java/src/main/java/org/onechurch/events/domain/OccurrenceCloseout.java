package org.onechurch.events.domain;
import java.time.Instant;
import java.util.UUID;
public record OccurrenceCloseout(UUID occurrenceId, String status, long version, String note, String followUp,
    String reviewHash, UUID updatedBy, Instant updatedAt) {
    public static OccurrenceCloseout open(UUID id) {
        return new OccurrenceCloseout(id, "OPEN", 0, "", "", "", null, null);
    }
    public OccurrenceCloseout close(EventOccurrence occurrence, long expected, String hash, String note,
        String followUp, boolean attendanceReviewed, boolean followUpReviewed, boolean unresolved, UUID actor,
        Instant now) {
        EventRules.version(version, expected);
        if (status.equals("CLOSED")) {
            throw EventException.conflict("This occurrence is already closed out.");
        }
        if (occurrence.status().equals("SCHEDULED") && now.isBefore(occurrence.endsAt())) {
            throw EventException.conflict("Close out after the gathering ends, or after it is cancelled.");
        }
        if (!attendanceReviewed || !followUpReviewed) {
            throw EventException.invalid("Review attendance, waitlists and delivery outcomes before closing out.");
        }
        String nextNote = EventRules.text(note, 2000);
        String nextFollowUp = unresolved ? EventRules.text(followUp, 2000) : EventRules.optional(followUp, 2000);
        return new OccurrenceCloseout(occurrenceId, "CLOSED", version + 1, nextNote, nextFollowUp, hash, actor,
            EventRules.instant(now));
    }
    public OccurrenceCloseout reopen(long expected, String reason, UUID actor, Instant now) {
        EventRules.version(version, expected);
        if (!status.equals("CLOSED")) {
            throw EventException.conflict("Only a closed occurrence can be reopened for review.");
        }
        return new OccurrenceCloseout(occurrenceId, "OPEN", version + 1, EventRules.text(reason, 2000), "", reviewHash,
            actor, EventRules.instant(now));
    }
}
