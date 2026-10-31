package org.onechurch.volunteering.application.command.changevolunteerassignment;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.volunteering.application.RosterPlanning;

public final class ChangeVolunteerAssignmentHandler {
    private final PermissionChecker auth;
    private final RosterPlanning roster;
    public ChangeVolunteerAssignmentHandler(PermissionChecker auth, RosterPlanning roster) {
        this.auth = auth;
        this.roster = roster;
    }
    public void handle(ChangeVolunteerAssignmentCommand c) {
        auth.requirePermission(c.actorId(), "volunteering.manage");
        roster.change(c.actorId(), c.id(), c.expectedVersion(), c.status(), c.reason());
    }
}
