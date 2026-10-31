package org.onechurch.payments.application.command.recordrefund;
import java.time.LocalDate;
import java.util.UUID;
public record RecordRefundCommand(UUID actorId, UUID orderId, UUID chargeId, String providerReference, long amountMinor,
    LocalDate refundedOn, String reason) {}
