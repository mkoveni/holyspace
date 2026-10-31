package org.onechurch.communications.application.contract;
import java.util.UUID;
public interface EmailDeliveries {
    void queue(String sourceContext, DeliverySnapshot approved);
    void cancel(UUID actor, UUID id, long expected, String reason);
    void retry(UUID actor, UUID id, long expected, String reason);
    void receipt(UUID actor, UUID id, String messageId, String key, String status, String reference);
}
