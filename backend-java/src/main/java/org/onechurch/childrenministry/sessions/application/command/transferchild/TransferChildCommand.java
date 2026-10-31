package org.onechurch.childrenministry.sessions.application.command.transferchild;

import java.util.UUID;
public record TransferChildCommand(UUID actorId, UUID attendanceId, long expectedVersion, UUID targetSessionId,
    String reason) {}
