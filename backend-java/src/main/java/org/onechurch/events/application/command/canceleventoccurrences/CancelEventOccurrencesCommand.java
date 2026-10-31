package org.onechurch.events.application.command.canceleventoccurrences;
import org.onechurch.events.application.OccurrenceChangeReview;

import java.util.UUID;
public record CancelEventOccurrencesCommand(UUID actorId, OccurrenceChangeReview.Draft draft, String reviewHash,
    String reason) {}
