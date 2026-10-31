package org.onechurch.events.application.command.rescheduleeventoccurrences;
import org.onechurch.events.application.OccurrenceChangeReview;

import java.util.UUID;
public record RescheduleEventOccurrencesCommand(UUID actorId, OccurrenceChangeReview.Draft draft, String reviewHash,
    String reason) {}
