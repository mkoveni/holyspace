package org.onechurch.volunteering.application.command.joinservingteam;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.volunteering.application.TeamManagement;

import java.util.UUID;

public final class JoinServingTeamHandler {
    private final PermissionChecker auth;
    private final TeamManagement teams;
    public JoinServingTeamHandler(PermissionChecker auth, TeamManagement teams) {
        this.auth = auth;
        this.teams = teams;
    }
    public UUID handle(JoinServingTeamCommand c) {
        auth.requirePermission(c.actorId(), "volunteering.manage");
        return teams.membership(c.actorId(), c.teamId(), c.expectedVersion(), c.volunteerId(), c.validFrom(),
            c.validUntil());
    }
}
