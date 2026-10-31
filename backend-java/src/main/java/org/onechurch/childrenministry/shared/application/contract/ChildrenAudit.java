package org.onechurch.childrenministry.shared.application.contract;

import java.time.Instant;
import java.util.UUID;
public interface ChildrenAudit {
    UUID record(UUID actorId, String action, UUID targetId, String reason, Instant at);
}
