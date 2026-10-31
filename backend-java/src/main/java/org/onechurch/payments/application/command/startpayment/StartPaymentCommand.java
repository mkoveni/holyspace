package org.onechurch.payments.application.command.startpayment;
import java.util.UUID;
public record StartPaymentCommand(UUID actorId, UUID requestId, UUID donorId, UUID fundId, long amountMinor,
    String provider, boolean recurring, boolean selfService) {
    public StartPaymentCommand(UUID actorId, UUID requestId, UUID donorId, UUID fundId, long amountMinor,
        String provider, boolean recurring) {
        this(actorId, requestId, donorId, fundId, amountMinor, provider, recurring, false);
    }
}
