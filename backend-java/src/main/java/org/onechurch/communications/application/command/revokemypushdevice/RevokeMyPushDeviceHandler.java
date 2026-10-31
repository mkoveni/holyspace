package org.onechurch.communications.application.command.revokemypushdevice;
import org.onechurch.communications.application.PushDeviceActions;
import org.onechurch.identityaccess.application.contract.AuthenticatedAccount;
public final class RevokeMyPushDeviceHandler {
    private final AuthenticatedAccount account;
    private final PushDeviceActions actions;
    public RevokeMyPushDeviceHandler(AuthenticatedAccount account, PushDeviceActions actions) {
        this.account = account;
        this.actions = actions;
    }
    public void handle(RevokeMyPushDeviceCommand c) {
        account.requireAccount();
        actions.revoke(c.deviceId());
    }
}
