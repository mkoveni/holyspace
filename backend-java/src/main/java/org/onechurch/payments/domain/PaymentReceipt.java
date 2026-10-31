package org.onechurch.payments.domain;
import java.time.Instant;
import java.util.UUID;
public record PaymentReceipt(UUID id, UUID orderId, UUID chargeId, UUID donorId, UUID contactId, UUID giftId,
    UUID deliveryId, UUID approvedBy, String reviewHash, String requestHash, String evidence, Instant createdAt) {}
