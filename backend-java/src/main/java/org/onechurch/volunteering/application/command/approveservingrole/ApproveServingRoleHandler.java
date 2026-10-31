package org.onechurch.volunteering.application.command.approveservingrole;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.volunteering.application.TeamManagement;

import java.util.UUID;

public final class ApproveServingRoleHandler {
    private final PermissionChecker auth;
    private final TeamManagement teams;
    public ApproveServingRoleHandler(PermissionChecker auth, TeamManagement teams) {
        this.auth = auth;
        this.teams = teams;
    }
    public UUID handle(ApproveServingRoleCommand c) {
        auth.requirePermission(c.actorId(), "volunteering.manage");
        return teams.approval(c.actorId(), c.teamId(), c.expectedVersion(), c.volunteerId(), c.roleId(), c.validFrom(),
            c.validUntil());
    }
}
