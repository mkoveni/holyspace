package org.onechurch.payments.domain;
import java.time.Instant;
import java.util.UUID;
public record SettlementReview(UUID id, UUID orderId, UUID chargeId, long version, String settlementReference,
    long feeMinor, long bankMinor, long refundedMinor, String note, UUID createdBy, Instant createdAt,
    String requestHash) {
    public SettlementReview {
        settlementReference = PaymentOrder.text(settlementReference, 200, "Settlement reference");
        note = PaymentOrder.text(note, 1000, "Review note");
        if (version < 1 || feeMinor < 0 || bankMinor < 0 || feeMinor > 100000000 || bankMinor > 100000000) {
            throw PaymentException.invalid("Enter valid fee and bank amounts.");
        }
    }
}
