package org.onechurch.payments.domain;
import java.time.Instant;
import java.time.LocalDate;
import java.util.UUID;
public record Charge(UUID id, UUID orderId, String provider, String mode, String reference, long amountMinor,
    LocalDate receivedOn, UUID giftId, Instant createdAt) {}
