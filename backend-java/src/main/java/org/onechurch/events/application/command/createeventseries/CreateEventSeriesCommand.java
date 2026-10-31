package org.onechurch.events.application.command.createeventseries;
import org.onechurch.events.application.ScheduleReview;

import java.util.UUID;
public record CreateEventSeriesCommand(UUID actorId, UUID requestId, UUID eventId, long expectedVersion,
    ScheduleReview.Draft schedule, String previewHash) {}
