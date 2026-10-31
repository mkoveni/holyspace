package org.onechurch.payments.application.command.resumecheckout;
import java.util.UUID;
public record ResumeCheckoutCommand(UUID actorId, UUID orderId, boolean selfService) {
    public ResumeCheckoutCommand(UUID actorId, UUID orderId) {
        this(actorId, orderId, false);
    }
}
