package org.onechurch.volunteering.application.command.revokeservingapproval;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.volunteering.application.TeamManagement;

public final class RevokeServingApprovalHandler {
    private final PermissionChecker auth;
    private final TeamManagement teams;
    public RevokeServingApprovalHandler(PermissionChecker auth, TeamManagement teams) {
        this.auth = auth;
        this.teams = teams;
    }
    public void handle(RevokeServingApprovalCommand c) {
        auth.requirePermission(c.actorId(), "volunteering.manage");
        teams.revoke(c.actorId(), c.teamId(), c.expectedVersion(), c.volunteerId(), c.recordId(), c.roleApproval());
    }
}
