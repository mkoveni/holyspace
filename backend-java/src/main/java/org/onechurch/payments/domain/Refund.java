package org.onechurch.payments.domain;
import java.time.Instant;
import java.time.LocalDate;
import java.util.UUID;
public record Refund(UUID id, UUID orderId, UUID chargeId, String provider, String mode, String reference,
    long amountMinor, LocalDate refundedOn, boolean applied, String reason, UUID createdBy, Instant createdAt) {}
