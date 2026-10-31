package org.onechurch.volunteering.application.command.updateservingrole;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.volunteering.application.TeamManagement;

public final class UpdateServingRoleHandler {
    private final PermissionChecker auth;
    private final TeamManagement teams;
    public UpdateServingRoleHandler(PermissionChecker auth, TeamManagement teams) {
        this.auth = auth;
        this.teams = teams;
    }
    public void handle(UpdateServingRoleCommand c) {
        auth.requirePermission(c.actorId(), "volunteering.manage");
        teams.updateRole(c.actorId(), c.teamId(), c.roleId(), c.expectedVersion(), c.name(), c.childrenRole(),
            c.active(), c.requiredCredentials());
    }
}
