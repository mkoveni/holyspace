package org.onechurch.events.application.command.closeeventoccurrence;
import java.util.UUID;
public record CloseEventOccurrenceCommand(UUID actorId, UUID occurrenceId, long expectedVersion, String reviewHash,
    String note, String followUp, boolean attendanceReviewed, boolean followUpReviewed) {}
