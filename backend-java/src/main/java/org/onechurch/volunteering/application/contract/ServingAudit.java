package org.onechurch.volunteering.application.contract;

import java.time.Instant;
import java.util.UUID;

public interface ServingAudit {
    default void record(UUID actor, String action, UUID target, Instant at) {
        record(actor, action, target, "", at);
    }
    void record(UUID actor, String action, UUID target, String reason, Instant at);
}
