package org.onechurch.payments.application.contract;
import org.onechurch.payments.domain.Charge;
import org.onechurch.payments.domain.Refund;
import org.onechurch.payments.domain.SettlementReview;

import java.time.Instant;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
public interface PaymentReader {
    record Summary(UUID id, UUID donorId, String donorName, UUID fundId, long amountMinor, String provider, String mode,
        boolean recurring, String status, String subscriptionStatus, String reviewReason, long version,
        Instant createdAt, String givingStatus) {}
    record Page(List<Summary> items, int total) {}
    record ChargeView(Charge charge, long refundedMinor, SettlementReview settlement, boolean needsReview,
        String givingStatus, String settlementStatus) {
        public ChargeView(Charge charge, long refundedMinor, SettlementReview settlement, boolean needsReview) {
            this(charge, refundedMinor, settlement, !charge.mode().equals("test") && needsReview,
                charge.mode().equals("test") ? "SIMULATION" : charge.giftId() == null ? "POSTING_PENDING" : "POSTED",
                charge.mode().equals("test") ? "NOT_APPLICABLE" : needsReview ? "NEEDS_REVIEW" : "MATCHED");
        }
    }
    record History(String action, String note, UUID actorId, Instant occurredAt) {}
    record Detail(Summary order, List<ChargeView> charges, List<Refund> refunds, List<History> history) {}
    Page list(int limit, int offset, boolean reviewOnly);
    Optional<Detail> detail(UUID id);
}
