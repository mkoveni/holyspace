package org.onechurch.communications.application.contract;
import java.util.UUID;
public interface PushWriter {
    void preference(UUID account, String topic, boolean enabled, long expectedVersion);
    void enqueue(UUID notification, UUID account, String topic);
}
