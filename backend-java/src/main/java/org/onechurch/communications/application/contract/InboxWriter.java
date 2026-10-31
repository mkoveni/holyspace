package org.onechurch.communications.application.contract;
import java.time.Instant;
import java.util.UUID;
public interface InboxWriter {
    void read(UUID account, UUID notification, boolean read, Instant at);
    void preference(UUID account, String topic, boolean enabled, long expectedVersion);
}
