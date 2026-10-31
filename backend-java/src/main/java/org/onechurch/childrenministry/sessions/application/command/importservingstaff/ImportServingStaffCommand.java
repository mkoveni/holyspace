package org.onechurch.childrenministry.sessions.application.command.importservingstaff;

import java.util.UUID;

public record ImportServingStaffCommand(UUID actorId, UUID sessionId, long expectedVersion, UUID assignmentId,
    String responsibility, boolean present) {}
