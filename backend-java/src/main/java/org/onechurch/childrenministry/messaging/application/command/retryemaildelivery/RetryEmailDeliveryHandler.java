package org.onechurch.childrenministry.messaging.application.command.retryemaildelivery;
import org.onechurch.childrenministry.messaging.application.DeliveryCommands;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class RetryEmailDeliveryHandler {
    private final PermissionChecker auth;
    private final DeliveryCommands commands;
    public RetryEmailDeliveryHandler(PermissionChecker auth, DeliveryCommands commands) {
        this.auth = auth;
        this.commands = commands;
    }
    public void handle(RetryEmailDeliveryCommand c) {
        auth.requirePermission(c.actorId(), "children.communications.send");
        auth.requirePermission("children.safeguarding");
        commands.retry(c.actorId(), c.deliveryId(), c.expectedVersion(), c.reason());
    }
}
