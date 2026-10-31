package org.onechurch.payments.application.command.startdonorpayment;
import java.util.UUID;
public record StartDonorPaymentCommand(UUID actorId, UUID requestId, UUID fundId, long amountMinor, String provider,
    boolean recurring) {}
