package org.onechurch.volunteering.application.command.createservingteam;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.volunteering.application.TeamManagement;

import java.util.UUID;

public final class CreateServingTeamHandler {
    private final PermissionChecker auth;
    private final TeamManagement teams;
    public CreateServingTeamHandler(PermissionChecker auth, TeamManagement teams) {
        this.auth = auth;
        this.teams = teams;
    }
    public UUID handle(CreateServingTeamCommand c) {
        auth.requirePermission(c.actorId(), "volunteering.manage");
        return teams.create(c.actorId(), c.name());
    }
}
