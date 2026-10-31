package org.onechurch.childrenministry.sessions.application.query.getroster;

import java.util.UUID;
public record GetRosterQuery(UUID actorId, UUID sessionId, boolean openOnly, int limit, int offset) {}
