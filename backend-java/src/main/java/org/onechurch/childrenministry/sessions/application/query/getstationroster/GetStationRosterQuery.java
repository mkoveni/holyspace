package org.onechurch.childrenministry.sessions.application.query.getstationroster;
import java.util.UUID;
public record GetStationRosterQuery(UUID actorId, UUID sessionId, int limit, int offset, boolean openOnly) {}
