package org.onechurch.communications.application.contract;

import java.time.Instant;
import java.util.Collection;
import java.util.List;
import java.util.Map;
import java.util.Optional;
import java.util.UUID;
public interface DeliveryReader {
    record Event(UUID id, String type, Instant occurredAt, String reference) {}
    record Summary(UUID id, UUID communicationId, String status, Instant scheduledAt, int attempts, String errorCode,
        boolean captured, long version) {}
    record Page(List<Summary> deliveries, long total, long failed, long waiting) {}
    record Receipt(String hash, UUID deliveryId) {}
    Map<UUID, Summary> summaries(Collection<UUID> communicationIds);
    Optional<DeliverySnapshot> delivery(UUID id);
    Optional<DeliverySnapshot> forCommunication(UUID communicationId);
    List<Event> events(UUID deliveryId, int limit, int offset);
    Page list(String status, int limit, int offset);
    Page listForSource(String sourceContext, String status, int limit, int offset);
    boolean belongsTo(UUID id, String sourceContext);
    List<UUID> due(Instant at, int limit);
    Optional<Receipt> receipt(String eventKey);
}
