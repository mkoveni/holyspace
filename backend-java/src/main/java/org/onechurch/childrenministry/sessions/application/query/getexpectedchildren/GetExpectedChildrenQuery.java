package org.onechurch.childrenministry.sessions.application.query.getexpectedchildren;
import java.util.UUID;
public record GetExpectedChildrenQuery(UUID actorId, UUID sessionId, int limit, int offset) {}
