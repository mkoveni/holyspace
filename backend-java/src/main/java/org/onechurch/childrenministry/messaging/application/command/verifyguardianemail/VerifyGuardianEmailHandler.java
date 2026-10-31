package org.onechurch.childrenministry.messaging.application.command.verifyguardianemail;
import org.onechurch.childrenministry.messaging.application.DeliveryCommands;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.util.UUID;
public final class VerifyGuardianEmailHandler {
    private final PermissionChecker auth;
    private final DeliveryCommands commands;
    public VerifyGuardianEmailHandler(PermissionChecker auth, DeliveryCommands commands) {
        this.auth = auth;
        this.commands = commands;
    }
    public UUID handle(VerifyGuardianEmailCommand c) {
        auth.requirePermission(c.actorId(), "children.safeguarding");
        return commands.verify(c.actorId(), c.communicationId(), c.contactId(), c.evidence(), c.validUntil());
    }
}
