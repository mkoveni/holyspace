package org.onechurch.childrenministry.messaging.application.command.queueguardianemail;
import org.onechurch.childrenministry.messaging.application.DeliveryCommands;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.util.UUID;
public final class QueueGuardianEmailHandler {
    private final PermissionChecker auth;
    private final DeliveryCommands commands;
    public QueueGuardianEmailHandler(PermissionChecker auth, DeliveryCommands commands) {
        this.auth = auth;
        this.commands = commands;
    }
    public UUID handle(QueueGuardianEmailCommand c) {
        auth.requirePermission(c.actorId(), "children.communications.send");
        auth.requirePermission("children.safeguarding");
        return commands.queue(c.actorId(), c.communicationId(), c.expectedVersion(), c.verificationId(), c.reviewHash(),
            c.scheduledAt());
    }
}
