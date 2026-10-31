package org.onechurch.volunteering.application.command.createservingrole;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.volunteering.application.TeamManagement;

import java.util.UUID;

public final class CreateServingRoleHandler {
    private final PermissionChecker auth;
    private final TeamManagement teams;
    public CreateServingRoleHandler(PermissionChecker auth, TeamManagement teams) {
        this.auth = auth;
        this.teams = teams;
    }
    public UUID handle(CreateServingRoleCommand c) {
        auth.requirePermission(c.actorId(), "volunteering.manage");
        return teams.role(c.actorId(), c.teamId(), c.expectedVersion(), c.name(), c.childrenRole(),
            c.requiredCredentials());
    }
}
