package org.onechurch.payments.application.command.reconcilepayment;
import java.util.UUID;
public record ReconcilePaymentCommand(UUID actorId, UUID requestId, UUID orderId, UUID chargeId, long expectedVersion,
    String settlementReference, long feeMinor, long bankMinor, String note) {}
