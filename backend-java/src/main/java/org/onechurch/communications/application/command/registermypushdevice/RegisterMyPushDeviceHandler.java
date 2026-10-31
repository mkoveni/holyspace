package org.onechurch.communications.application.command.registermypushdevice;
import org.onechurch.communications.application.PushDeviceActions;
import org.onechurch.identityaccess.application.contract.AuthenticatedAccount;
public final class RegisterMyPushDeviceHandler {
    private final AuthenticatedAccount account;
    private final PushDeviceActions actions;
    public RegisterMyPushDeviceHandler(AuthenticatedAccount account, PushDeviceActions actions) {
        this.account = account;
        this.actions = actions;
    }
    public void handle(RegisterMyPushDeviceCommand c) {
        account.requireAccount();
        actions.register(c.deviceId(), c.installationSecret(), c.token(), c.platform(), c.projectId());
    }
}
