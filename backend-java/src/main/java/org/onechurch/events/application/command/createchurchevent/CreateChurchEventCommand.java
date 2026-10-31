package org.onechurch.events.application.command.createchurchevent;
import org.onechurch.events.application.ScheduleReview;

import java.util.UUID;
public record CreateChurchEventCommand(UUID actorId, UUID requestId, String title, String type, String description,
    ScheduleReview.Draft schedule, String previewHash) {}
