package org.onechurch.volunteering.application.command.invitevolunteer;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.volunteering.application.RosterPlanning;

import java.util.UUID;

public final class InviteVolunteerHandler {
    private final PermissionChecker auth;
    private final RosterPlanning roster;
    public InviteVolunteerHandler(PermissionChecker auth, RosterPlanning roster) {
        this.auth = auth;
        this.roster = roster;
    }
    public UUID handle(InviteVolunteerCommand c) {
        auth.requirePermission(c.actorId(), "volunteering.manage");
        return roster.invite(c.actorId(), c.assignmentId(), c.expectedVersion(), c.expiresAt());
    }
}
