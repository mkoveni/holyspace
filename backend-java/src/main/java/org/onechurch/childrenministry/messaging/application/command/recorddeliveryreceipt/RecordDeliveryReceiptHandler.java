package org.onechurch.childrenministry.messaging.application.command.recorddeliveryreceipt;
import org.onechurch.childrenministry.messaging.application.DeliveryCommands;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class RecordDeliveryReceiptHandler {
    private final PermissionChecker auth;
    private final DeliveryCommands commands;
    public RecordDeliveryReceiptHandler(PermissionChecker auth, DeliveryCommands commands) {
        this.auth = auth;
        this.commands = commands;
    }
    public void handle(RecordDeliveryReceiptCommand c) {
        auth.requirePermission(c.actorId(), "children.communications.receipts");
        commands.receipt(c.actorId(), c.deliveryId(), c.messageId(), c.eventKey(), c.status(), c.reference());
    }
}
