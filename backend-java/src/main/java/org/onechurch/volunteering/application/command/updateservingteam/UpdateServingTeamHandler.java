package org.onechurch.volunteering.application.command.updateservingteam;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.volunteering.application.TeamManagement;

public final class UpdateServingTeamHandler {
    private final PermissionChecker auth;
    private final TeamManagement teams;
    public UpdateServingTeamHandler(PermissionChecker auth, TeamManagement teams) {
        this.auth = auth;
        this.teams = teams;
    }
    public void handle(UpdateServingTeamCommand c) {
        auth.requirePermission(c.actorId(), "volunteering.manage");
        teams.update(c.actorId(), c.teamId(), c.expectedVersion(), c.name(), c.active());
    }
}
