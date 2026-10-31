package org.onechurch.childrenministry.sessions.application.command.cancelsessionseries;
import java.util.UUID;
public record CancelSessionSeriesCommand(UUID actorId, UUID seriesId, long expectedVersion, String reason) {}
