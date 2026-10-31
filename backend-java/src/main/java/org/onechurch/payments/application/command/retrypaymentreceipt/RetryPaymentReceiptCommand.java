package org.onechurch.payments.application.command.retrypaymentreceipt;
import java.util.UUID;
public record RetryPaymentReceiptCommand(UUID actorId, UUID orderId, UUID receiptId, long expectedVersion,
    String reason) {}
