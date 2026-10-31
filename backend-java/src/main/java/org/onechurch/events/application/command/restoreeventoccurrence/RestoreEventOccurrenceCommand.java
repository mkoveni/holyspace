package org.onechurch.events.application.command.restoreeventoccurrence;
import org.onechurch.events.application.OccurrenceChangeReview;

import java.util.UUID;
public record RestoreEventOccurrenceCommand(UUID actorId, OccurrenceChangeReview.Draft draft, String reviewHash,
    String reason) {}
