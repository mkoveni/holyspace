package org.onechurch.events.application.command.configurememberevent;
import org.onechurch.events.application.MemberEventActions;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class ConfigureMemberEventHandler {
    private final MemberEventActions actions;
    private final PermissionChecker auth;
    public ConfigureMemberEventHandler(MemberEventActions actions, PermissionChecker auth) {
        this.actions = actions;
        this.auth = auth;
    }
    public void handle(ConfigureMemberEventCommand c) {
        auth.requirePermission(c.actorId(), "events.manage");
        actions.configure(c.actorId(), c.eventId(), c.enabled(), c.expectedVersion());
    }
}
