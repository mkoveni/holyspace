package org.onechurch.childrenministry.messaging.application.command.cancelemaildelivery;
import org.onechurch.childrenministry.messaging.application.DeliveryCommands;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class CancelEmailDeliveryHandler {
    private final PermissionChecker auth;
    private final DeliveryCommands commands;
    public CancelEmailDeliveryHandler(PermissionChecker auth, DeliveryCommands commands) {
        this.auth = auth;
        this.commands = commands;
    }
    public void handle(CancelEmailDeliveryCommand c) {
        auth.requirePermission(c.actorId(), "children.communications.send");
        auth.requirePermission("children.safeguarding");
        commands.cancel(c.actorId(), c.deliveryId(), c.expectedVersion(), c.reason());
    }
}
