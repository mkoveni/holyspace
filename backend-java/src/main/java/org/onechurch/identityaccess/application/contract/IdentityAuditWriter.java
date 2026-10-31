package org.onechurch.identityaccess.application.contract;

import java.time.Instant;
import java.util.UUID;

public interface IdentityAuditWriter {
    void audit(UUID actorId, String action, UUID targetId, Instant now);
}
