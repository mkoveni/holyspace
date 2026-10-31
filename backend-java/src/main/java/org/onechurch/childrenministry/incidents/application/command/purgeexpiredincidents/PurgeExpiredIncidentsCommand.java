package org.onechurch.childrenministry.incidents.application.command.purgeexpiredincidents;
import java.util.UUID;
public record PurgeExpiredIncidentsCommand(UUID actorId, int limit) {}
