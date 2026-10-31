package org.onechurch.childrenministry.sessions.application.query.getpickupcollectors;
import java.time.Instant;
import java.util.UUID;
public record PickupCollector(UUID authorizationId, UUID collectorId, String name, Instant validUntil) {}
