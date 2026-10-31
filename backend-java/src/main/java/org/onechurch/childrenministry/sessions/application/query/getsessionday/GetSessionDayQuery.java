package org.onechurch.childrenministry.sessions.application.query.getsessionday;
import java.util.UUID;
public record GetSessionDayQuery(UUID actorId, UUID sessionId, String search, String view, int limit, int offset) {}
