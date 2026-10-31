package org.onechurch.payments.application.command.managesubscription;
import java.util.UUID;
public record ManageSubscriptionCommand(UUID actorId, UUID orderId, long expectedVersion, boolean cancel,
    boolean selfService) {
    public ManageSubscriptionCommand(UUID actorId, UUID orderId, long expectedVersion, boolean cancel) {
        this(actorId, orderId, expectedVersion, cancel, false);
    }
}
