package org.onechurch.communications.application.contract;
import java.util.UUID;
public interface DeliverySources {
    String context(UUID deliveryId);
    void associate(UUID deliveryId, String context);
}
