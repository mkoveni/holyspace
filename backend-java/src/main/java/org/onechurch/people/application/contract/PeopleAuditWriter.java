package org.onechurch.people.application.contract;

import java.time.Instant;
import java.util.UUID;

public interface PeopleAuditWriter {
    void audit(UUID actorId, String action, String targetReference, Instant at);

    default void audit(UUID actorId, String action, UUID targetId, Instant at) {
        audit(actorId, action, targetId.toString(), at);
    }
}
