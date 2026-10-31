package org.onechurch.volunteering.application.command.updatevolunteershift;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.volunteering.application.RosterPlanning;

public final class UpdateVolunteerShiftHandler {
    private final PermissionChecker auth;
    private final RosterPlanning roster;
    public UpdateVolunteerShiftHandler(PermissionChecker auth, RosterPlanning roster) {
        this.auth = auth;
        this.roster = roster;
    }
    public void handle(UpdateVolunteerShiftCommand c) {
        auth.requirePermission(c.actorId(), "volunteering.manage");
        roster.update(c.actorId(), c.id(), c.expectedVersion(), c.startsAt(), c.endsAt(), c.positions(), c.status());
    }
}
