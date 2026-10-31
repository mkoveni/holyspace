package org.onechurch.payments.application.command.approvepaymentreceipt;
import java.util.UUID;
public record ApprovePaymentReceiptCommand(UUID actorId, UUID requestId, UUID orderId, UUID chargeId, UUID contactId,
    String reviewHash, String evidence) {
    @Override
    public String toString() {
        return "ApprovePaymentReceiptCommand[redacted]";
    }
}
