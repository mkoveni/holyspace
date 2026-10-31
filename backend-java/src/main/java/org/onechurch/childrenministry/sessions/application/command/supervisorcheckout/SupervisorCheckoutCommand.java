package org.onechurch.childrenministry.sessions.application.command.supervisorcheckout;

import java.util.UUID;
public record SupervisorCheckoutCommand(UUID actorId, UUID attendanceId, long expectedVersion, UUID requestId,
    UUID collectorId, boolean identityVerified, String reason) {}
