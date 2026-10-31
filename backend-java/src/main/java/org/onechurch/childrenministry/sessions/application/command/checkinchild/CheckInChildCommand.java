package org.onechurch.childrenministry.sessions.application.command.checkinchild;

import java.util.UUID;
public record CheckInChildCommand(UUID actorId, UUID sessionId, UUID childId, UUID requestId) {}
