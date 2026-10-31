package org.onechurch.volunteering.application.command.createvolunteershift;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.volunteering.application.RosterPlanning;

import java.util.UUID;

public final class CreateVolunteerShiftHandler {
    private final PermissionChecker auth;
    private final RosterPlanning roster;
    public CreateVolunteerShiftHandler(PermissionChecker auth, RosterPlanning roster) {
        this.auth = auth;
        this.roster = roster;
    }
    public UUID handle(CreateVolunteerShiftCommand c) {
        auth.requirePermission(c.actorId(), "volunteering.manage");
        return roster.create(c.actorId(), c.draft());
    }
}
