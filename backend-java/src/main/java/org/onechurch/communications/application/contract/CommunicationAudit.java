package org.onechurch.communications.application.contract;
import java.time.Instant;
import java.util.UUID;
public interface CommunicationAudit {
    void record(UUID actor, String action, UUID target, String detail, Instant at);
}
