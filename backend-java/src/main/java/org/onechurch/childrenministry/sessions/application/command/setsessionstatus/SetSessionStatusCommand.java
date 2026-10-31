package org.onechurch.childrenministry.sessions.application.command.setsessionstatus;

import org.onechurch.childrenministry.sessions.domain.SessionStatus;

import java.util.UUID;
public record SetSessionStatusCommand(UUID actorId, UUID sessionId, long expectedVersion, SessionStatus status,
    String reason, boolean acknowledgeIncidents) {
    public SetSessionStatusCommand(UUID actorId, UUID sessionId, long expectedVersion, SessionStatus status,
        String reason) {
        this(actorId, sessionId, expectedVersion, status, reason, false);
    }
}
