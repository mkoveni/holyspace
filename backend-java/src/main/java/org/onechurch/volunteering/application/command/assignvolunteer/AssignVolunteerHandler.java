package org.onechurch.volunteering.application.command.assignvolunteer;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.volunteering.application.RosterPlanning;

import java.util.UUID;

public final class AssignVolunteerHandler {
    private final PermissionChecker auth;
    private final RosterPlanning roster;
    public AssignVolunteerHandler(PermissionChecker auth, RosterPlanning roster) {
        this.auth = auth;
        this.roster = roster;
    }
    public UUID handle(AssignVolunteerCommand c) {
        auth.requirePermission(c.actorId(), "volunteering.manage");
        return roster.assign(c.actorId(), c.requestId(), c.shiftId(), c.expectedVersion(), c.volunteerId(), c.status());
    }
}
