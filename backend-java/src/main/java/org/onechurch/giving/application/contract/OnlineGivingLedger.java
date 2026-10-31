package org.onechurch.giving.application.contract;
import java.time.LocalDate;
import java.util.UUID;
/** Trusted Payments integration only. This capability is never exposed as an HTTP payment assertion. */
public interface OnlineGivingLedger {
    record Donor(String name) {}
    Donor validate(UUID donorId, UUID fundId);
    UUID post(UUID chargeId, UUID donorId, UUID fundId, long amountMinor, String reference, LocalDate receivedOn,
        UUID initiatedBy);
    void refund(UUID refundId, UUID giftId, long amountMinor, String reference, LocalDate refundedOn, UUID initiatedBy);
}
