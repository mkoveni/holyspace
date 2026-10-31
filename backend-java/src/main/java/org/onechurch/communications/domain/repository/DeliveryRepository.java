package org.onechurch.communications.domain.repository;
import org.onechurch.communications.domain.EmailDelivery;

import java.time.Instant;
import java.util.Optional;
import java.util.UUID;
public interface DeliveryRepository {
    void insert(EmailDelivery delivery);
    Optional<EmailDelivery> lock(UUID id);
    void save(EmailDelivery delivery);
    void event(UUID deliveryId, String type, Instant at, UUID actorId, String eventKey, String eventHash,
        String reference);
    int recoverStale(Instant before, Instant now);
}
