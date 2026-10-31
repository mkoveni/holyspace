package org.onechurch.childrenministry.messaging.application.command.revokeguardianemail;
import org.onechurch.childrenministry.messaging.application.DeliveryCommands;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class RevokeGuardianEmailHandler {
    private final PermissionChecker auth;
    private final DeliveryCommands commands;
    public RevokeGuardianEmailHandler(PermissionChecker auth, DeliveryCommands commands) {
        this.auth = auth;
        this.commands = commands;
    }
    public void handle(RevokeGuardianEmailCommand c) {
        auth.requirePermission(c.actorId(), "children.safeguarding");
        commands.revoke(c.actorId(), c.verificationId(), c.reason());
    }
}
