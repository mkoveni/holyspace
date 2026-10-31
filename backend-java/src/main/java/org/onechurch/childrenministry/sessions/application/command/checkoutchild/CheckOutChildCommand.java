package org.onechurch.childrenministry.sessions.application.command.checkoutchild;

import java.util.UUID;
public record CheckOutChildCommand(UUID actorId, UUID attendanceId, long expectedVersion, UUID requestId,
    UUID collectorId, boolean identityVerified, String pickupCode) {
    @Override
    public String toString() {
        return "CheckOutChildCommand[REDACTED]";
    }
}
